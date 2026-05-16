# Deck

**Job-class observability and safe cancellation for Laravel apps running [Horizon](https://laravel.com/docs/horizon).**

> Horizon flies the workers. Deck runs the operation.

Horizon excels at supervising Redis workers, balancing queues, and showing a short-lived window of recent activity. Deck complements it with a **durable control plane**: when each job class last ran, execution history, search and filters, and cooperative cancellation for long-running work.

Deck does **not** replace Horizon. Keep `php artisan horizon` in production; open Deck when you need job-class history and ops actions Horizon does not provide.

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

The install command publishes `config/deck.php` and migrations. Register the dashboard route by visiting `/deck` (prefix is configurable).

### Horizon authentication & choice prompt

Deck uses the **same authorization as Horizon** by default. Define access in your `HorizonServiceProvider` as you already do for `/horizon`.

When both are installed, **`deck:install` adds a middleware** so the first visit to `/horizon` shows a short prompt: go to **Deck** (job-class history, cancel) or **continue to Horizon** (workers, throughput, supervisors). Users can **remember their choice** for the session. Horizon’s runtime (`php artisan horizon`) is never affected.

Set `DECK_HORIZON_PROMPT=false` to skip the prompt and use Horizon as usual.

### UI

The dashboard is built with **Livewire** and **Alpine.js**, using Deck’s **design system** (`<x-deck::*>` components) for a clean, Laravel-native look. See [IMPLEMENTATION.md](IMPLEMENTATION.md) for layout and component details.

---

## Quick start

### 1. Record runs automatically

Once installed, Deck listens to queue events and records starts, completions, and failures. No change is required for basic history.

### 2. View the dashboard

Open `/deck` (or your configured prefix) to see:

- All observed job classes
- Last started / last finished
- Last status and duration
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
| `retention_days` | How long to keep execution rows |
| `cancel_ttl_seconds` | Redis TTL for cancel flags |
| `long_running_threshold_seconds` | Highlight runs exceeding this duration |
| `store_context` | Persist opt-in `deckContext()` from jobs (default: `false`) |

See [IMPLEMENTATION.md](IMPLEMENTATION.md) for the full config surface as features land.

---

## Artisan commands

| Command | Description |
|---------|-------------|
| `deck:install` | Publish config and migrations |
| `deck:prune` | Remove execution rows older than retention |

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
| **V1** | Tags, pending cancel, alerts, prune, long-running highlights |
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
