# Deck

**Job-class observability and safe cancellation for Laravel apps running [Horizon](https://laravel.com/docs/horizon).**

> Horizon flies the workers. Deck runs the operation.

Horizon excels at supervising Redis workers, balancing queues, and showing a short-lived window of recent activity. Deck complements it with a **durable control plane**: when each job class last ran, execution history, search and filters, and cooperative cancellation for long-running work.

Deck does **not** replace Horizon. Keep `php artisan horizon` in production; open Deck when you need job-class history and ops actions Horizon does not provide.

**Deck Cloud (future):** The package tags every run with `DECK_PROJECT` and `DECK_ENVIRONMENT` so one hosted dashboard can unify all your apps. See [IMPLEMENTATION.md](IMPLEMENTATION.md#deck-cloud-future).

---

## Why Deck?

| Horizon gives you | Deck adds |
|-------------------|-----------|
| Worker supervision and auto-balancing | Per job-class **last run** and status |
| Recent jobs in Redis (short retention) | **Durable execution log** in your database |
| Failed job retry UI | **Search and filter** by class, queue, connection |
| Throughput and wait-time metrics | **Cooperative cancel** for opt-in jobs |
| Tags on failed jobs (limited elsewhere) | **Tags** captured on every recorded run |

Common pain points Deck targets: jobs disappearing from Recent before delayed runs execute, no answer to “when did `ProcessInvoice` last succeed?”, and no safe way to cancel a specific pending or running job from a dashboard.

---

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13
- [Laravel Horizon](https://laravel.com/docs/horizon) 5.x (Redis queues)
- A database (MySQL, PostgreSQL, SQLite, etc.) for Deck’s execution log
- Redis (shared with Horizon for queues and cancel flags)

---

## Installation

```bash
composer require tormjens/deck
```

Publish config and migrations, then migrate:

```bash
php artisan deck:install
php artisan migrate
```

The install command publishes `config/deck.php`, migrations, and precompiled CSS to `public/vendor/deck`. Visit `/deck` (prefix is configurable).

### Separate database (optional)

To keep execution history off your primary database, add a connection in `config/database.php`, set `DECK_DB_CONNECTION` to its name, and migrate that connection only:

```bash
# .env
DECK_DB_CONNECTION=deck

php artisan migrate --database=deck
```

### Horizon authentication & choice prompt

Deck uses the **same authorization as Horizon** by default. Define access in your `HorizonServiceProvider` as you already do for `/horizon`.

When both are installed, **`deck:install` adds a middleware** so the first visit to `/horizon` shows a short prompt: go to **Deck** (job-class history, cancel) or **continue to Horizon** (workers, throughput, supervisors). Users can **remember their choice** for the session. Horizon’s runtime (`php artisan horizon`) is never affected.

Set `DECK_HORIZON_PROMPT=false` to skip the prompt and use Horizon as usual.

### UI

The dashboard ships with **precompiled Tailwind CSS** — no Vite or `@source` setup in your app. Livewire powers interactivity; run `deck:install` to publish assets to `public/vendor/deck`. See [IMPLEMENTATION.md](IMPLEMENTATION.md) for layout details.

---

## Quick start

### 1. Record runs automatically

Once installed, Deck listens to queue events and records starts, completions, and failures. No change is required for basic history.

### 2. View the dashboard

Open `/deck` (or your configured prefix) for the **overview** — running jobs, recent failures, and latest activity. From there:

- **Job classes** — per-class history, success rate, filters
- **Activity** — searchable execution log across all classes (status, queue, UUID)
- **Cancel** — cooperative stop for running jobs (Horizon does not offer this)
- Drill-down into recent executions

### 3. Make jobs cancellable (opt-in)

Long-running jobs should check a cancel flag between steps:

```php
use TorMorten\Deck\Middleware\Cancellable;
use TorMorten\Deck\Support\JobCancellation;

class GenerateReport implements ShouldQueue
{
    public function middleware(): array
    {
        return [new Cancellable];
    }

    public function handle(): void
    {
        foreach ($this->steps() as $step) {
            JobCancellation::throwIfCancelled($this->job);

            $step->run();
        }
    }
}
```

Cancel from the dashboard or programmatically:

```php
use TorMorten\Deck\Facades\Deck;

Deck::cancel($jobUuid);
```

Cancellation is **cooperative**: the worker checks the flag between steps. Deck does not force-kill PHP processes.

### Cancel queued jobs (best effort)

On **Activity**, cancel a job by UUID before a worker picks it up. Deck sets the cancel flag and attempts to remove the payload from Redis queues (when using the Redis driver). This can race with workers — treat it as best effort.

### Retry failed jobs

Failed executions show a **Retry** action. Deck prefers Horizon's failed-job store, then Laravel's `failed_jobs` table, then a parameterless re-dispatch when the job class allows it.

### Stale job alerts (optional)

```php
// config/deck.php
'alerts' => [
    'enabled' => env('DECK_ALERTS_ENABLED', false),
    'notification' => \App\Notifications\DeckStaleJobsNotification::class,
    'notifiable' => \App\Models\User::class, // resolved from the container
    'stale_jobs' => [
        \App\Jobs\SyncInventory::class => ['max_age_hours' => 24],
    ],
],
```

Schedule in `routes/console.php`:

```php
Schedule::command('deck:check-alerts')->hourly();
```

Your notification receives a `Collection` of `TorMorten\Deck\Data\DeckStaleJobAlert` instances.

---

## Configuration

Published `config/deck.php` includes:

| Option | Purpose |
|--------|---------|
| `route_prefix` | Dashboard URL prefix (default: `deck`) |
| `middleware` | Route middleware stack |
| `auth` | Authorization callback (`null` = use Horizon) |
| `horizon.prompt_on_visit` | Show Horizon vs Deck prompt (default: `true`) |
| `horizon.remember_choice` | Store choice in session (default: `true`) |
| `database_connection` | Laravel DB connection for `deck_*` tables (`DECK_DB_CONNECTION`; default: app default) |
| `retention_days` | How long to keep execution rows |
| `cancel_ttl_seconds` | Redis TTL for cancel flags |
| `long_running_threshold_seconds` | Highlight runs exceeding this duration |
| `store_context` | Persist opt-in `deckContext()` from jobs (default: `false`) |
| `alerts.*` | Stale-job rules, notification class, notifiable |

See [IMPLEMENTATION.md](IMPLEMENTATION.md) for the full config surface.

---

## Artisan commands

| Command | Description |
|---------|-------------|
| `deck:install` | Publish config and migrations |
| `deck:prune` | Remove execution rows older than retention |
| `deck:check-alerts` | Evaluate stale-job rules and notify (when enabled) |

---

## Relationship to Horizon

```text
┌─────────────────────────────────────────────────────────┐
│  Your Laravel app                                        │
├──────────────────────────┬──────────────────────────────┤
│  Horizon (runtime)       │  Deck (control plane)       │
│  • horizon artisan       │  • Queue event listeners    │
│  • Workers & balancing   │  • DB execution log         │
│  • /horizon dashboard    │  • /deck dashboard          │
│  • Recent/failed in Redis│  • Last run, search, cancel │
└──────────────────────────┴──────────────────────────────┘
                          Redis queues
```

Use **Horizon** for worker health, scaling, and failed-job retry. Use **Deck** for job-class history, discovery, and cancellation.

---

## Roadmap

Development is phased. See **[IMPLEMENTATION.md](IMPLEMENTATION.md)** for the detailed plan, schema, and milestones.

| Phase | Focus |
|-------|--------|
| **MVP** | Execution log, per-class aggregates, dashboard, cooperative cancel |
| **V1** | Filters (queue, connection, tag), pending cancel, stale-job alerts, long-running highlights |
| **V2** | Runtime rollups, progress API, queue admin actions |

---

## Testing

```bash
cd packages/deck && composer test
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## License

MIT © [Tor Morten Jensen](https://github.com/tormjens). See [LICENSE.md](LICENSE.md).
