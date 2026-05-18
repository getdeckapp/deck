# Deck

**Job-class observability and safe cancellation for Laravel apps running [Horizon](https://laravel.com/docs/horizon).**

> Horizon flies the workers. Deck runs the operation.

Horizon excels at supervising Redis workers, balancing queues, and showing a short-lived window of recent activity. Deck complements it with a **durable control plane**: when each job class last ran, execution history, search and filters, cooperative cancellation, and dispatch blocking for incident response.

Deck does **not** replace Horizon. Keep `php artisan horizon` in production; use Deck when you need job-class history and operational actions Horizon does not provide.

---

## Contents

- [Why Deck?](#why-deck)
- [Requirements](#requirements)
- [Installation](#installation)
- [Production best practices](#production-best-practices)
- [Usage](#usage)
- [Configuration](#configuration)
- [Artisan commands](#artisan-commands)
- [Relationship to Horizon](#relationship-to-horizon)
- [Development](#development)
- [License](#license)

---

## Why Deck?

| Horizon gives you | Deck adds |
|-------------------|-----------|
| Worker supervision and auto-balancing | Per job-class **last run** and status |
| Recent jobs in Redis (short retention) | **Durable execution log** in your database |
| Failed job retry UI | **Search and filter** by class, queue, connection, tag |
| Throughput and wait-time metrics | **Cooperative cancel** for opt-in jobs |
| Tags on failed jobs (limited elsewhere) | **Tags** on every recorded run |
| — | **Block job classes** at dispatch during incidents |
| — | **Stale-job** and **unprocessed-queue** alerts |

Common pain points Deck targets: jobs disappearing from Recent before delayed runs execute, no answer to “when did `ProcessInvoice` last succeed?”, and no safe way to stop or block a job class from a dashboard.

Every recorded row is tagged with `project` and `environment` (Deck Cloud–ready). See [IMPLEMENTATION.md](IMPLEMENTATION.md#deck-cloud-future) for the multi-tenant roadmap.

---

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13
- [Laravel Horizon](https://laravel.com/docs/horizon) 5.x (Redis queues recommended)
- A database for Deck’s execution log (MySQL, PostgreSQL, SQLite, etc.)
- Redis for queues (Horizon) and for cancel/block flags (typically the same Redis instance)

---

## Installation

```bash
composer require tormjens/deck
php artisan deck:install
```

`deck:install` publishes `config/deck.php`, migrations, and precompiled CSS to `public/vendor/deck`.

Run migrations on the connection Deck will use. For the default application database:

```bash
php artisan migrate
```

When using a [dedicated database](#dedicated-database-recommended), migrate only the Deck migration files on that connection (see that section for paths).

Open `/deck` (prefix is configurable via `deck.route_prefix`).

### Horizon authorization

Deck uses **the same authorization as Horizon** by default. Define access in `App\Providers\HorizonServiceProvider` as you already do for `/horizon`.

When both dashboards are installed, `deck:install` can add middleware so the first visit to `/horizon` offers **Deck** or **Horizon**. Set `DECK_HORIZON_PROMPT=false` to disable the prompt. Horizon’s runtime (`php artisan horizon`) is never affected.

### UI assets

The dashboard ships with **precompiled Tailwind CSS** — no Vite or `@source` setup in your app. Re-run `deck:install --force` after upgrading the package to refresh published assets.

---

## Production best practices

Use this checklist when deploying Deck to production.

### Dedicated database (recommended)

Deck appends a row on every job start, completion, and failure. On busy queues, that load belongs on a **separate database**, not your primary application DB.

1. Add a connection in `config/database.php`:

```php
'connections' => [
  'deck' => [
    'driver' => 'mysql',
    'host' => env('DECK_DB_HOST', '127.0.0.1'),
    'database' => env('DECK_DB_DATABASE', 'deck'),
    'username' => env('DECK_DB_USERNAME', 'deck'),
    'password' => env('DECK_DB_PASSWORD', ''),
    // ...
  ],
],
```

2. Point Deck at that connection:

```env
DECK_DB_CONNECTION=deck
```

3. Run **only** the Deck migrations on that connection. After `deck:install`, published files live in `database/migrations/` and include `deck` in the filename. Deck migrations call `DeckDatabase` and respect `DECK_DB_CONNECTION`:

```bash
# Replace timestamps with your published filenames
php artisan migrate --database=deck --path=database/migrations/2025_01_01_000000_create_deck_tables.php
php artisan migrate --database=deck --path=database/migrations/2025_01_01_000001_add_project_and_environment_to_deck_tables.php
php artisan migrate --database=deck --path=database/migrations/2025_01_01_000002_add_exception_trace_to_deck_job_executions.php
```

Do **not** run a blanket `php artisan migrate --database=deck` unless that database is dedicated to Deck and you intend to run every pending migration in `database/migrations/` against it.

4. Grant the app user read/write on `deck_*` tables only. No cross-DB joins are required.

If `DECK_DB_CONNECTION` is unset, Deck uses Laravel’s default connection.

### Stable installation identity

Set explicit values per deployable and environment so stats and history never collide (especially across staging and production, or multiple apps):

```env
DECK_PROJECT=billing-api
DECK_ENVIRONMENT=production
```

Use a stable `DECK_PROJECT` per service (not machine-specific names). Defaults fall back to `APP_NAME` and `APP_ENV`, which are often too generic for multi-app teams.

### Retention and pruning

Execution rows accumulate quickly. Tune retention and prune on a schedule:

```env
DECK_RETENTION_DAYS=90
```

In `routes/console.php`:

```php
Schedule::command('deck:prune')->daily();
```

`deck:prune` deletes rows older than `retention_days` from `deck_job_executions`. Adjust retention for compliance and disk budget; shorter retention reduces database size on high-throughput apps.

### Secure the dashboard

- Restrict `/deck` to operators only — reuse Horizon’s gate or set `deck.auth` to a callable that returns `true` only for authorized users.
- Do not expose Deck on a public URL without authentication.
- Treat cancel, block, and retry actions as **privileged**; they affect production queues.

```php
// config/deck.php — example custom gate
'auth' => fn ($request) => $request->user()?->can('view-horizon') ?? false,
```

### Redis cache for cancel and block flags

Cancel and block state live in **cache** (Redis recommended in production), not in the Deck database. All Horizon workers must see the same store.

```env
# Use the same Redis as queues, or a dedicated cache connection
DECK_CANCEL_CACHE_STORE=redis
DECK_BLOCK_CACHE_STORE=redis
```

If unset, block flags fall back to the same store as cancel (`cancel_cache_store`, then `cache.default`). In multi-server deployments, **never** use `file` or `array` drivers for these flags.

### High job volume

- Prefer a **dedicated database** and appropriate indexes (included in migrations).
- Keep `store_context` disabled unless you need opt-in debug fields (see below).
- Lower `retention_days` or prune more aggressively when volume is extreme.
- Deck records synchronously on queue events; ensure the Deck DB connection has sensible pool limits and monitoring.

### Schedule operational commands

| Command | Suggested schedule | Purpose |
|---------|-------------------|---------|
| `deck:prune` | Daily | Remove old execution history |
| `deck:check-alerts` | Hourly (when alerts enabled) | Stale jobs and unprocessed queues |

### Payloads and sensitive data

Deck **does not** store serialized job payloads by default. Exception messages are truncated; stack traces are capped (`DECK_EXCEPTION_TRACE_BYTES`, default 64 KB).

To attach safe, scalar debug context from a job, implement `TorMorten\Deck\Contracts\ExposesDeckContext` and enable:

```env
DECK_STORE_CONTEXT=true
```

Only expose non-sensitive scalars from `deckContext()` — never tokens, passwords, or PII.

### Incident response

- **Block a job class** — stops new dispatches and records a `blocked` execution; optional cancel of in-flight runs. Use the job-class UI or `Deck::blockClass()`.
- **Cancel running jobs** — cooperative; jobs must use `Cancellable` middleware and check `JobCancellation::throwIfCancelled()`.
- **Retry failed jobs** — from Activity; uses Horizon’s failed-job store when available.

During incidents, block first to stop the bleed, then cancel long-running work, then retry failures after a fix is deployed.

### Unprocessed queue warnings

When Horizon is installed, Deck can surface queues that have pending jobs but no assigned workers (`DECK_UNPROCESSED_QUEUES_ENABLED`, default `true`). Review the **Workers** page after deploys or Horizon config changes.

---

## Usage

### Automatic recording

After installation, Deck listens to queue events and records starts, completions, and failures. No code changes are required for basic history.

### Dashboard

| Route | Purpose |
|-------|---------|
| `/deck` | Overview, charts, recent failures, queue health |
| `/deck/classes` | Per-class stats and history |
| `/deck/activity` | Searchable execution log |
| `/deck/workers` | Horizon snapshot and unprocessed queues |

### Cooperative cancellation (opt-in)

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

Cancellation is **cooperative** — Deck does not force-kill PHP workers. Pending cancel on Redis queues is **best effort** and can race with workers.

### Block a job class

Blocked classes are intercepted at dispatch (never pushed to the queue) and recorded with status `blocked`:

```php
use TorMorten\Deck\Facades\Deck;

Deck::blockClass(\App\Jobs\SyncInventory::class, until: now()->addHour(), reason: 'Upstream API outage');

// Later
Deck::unblockClass(\App\Jobs\SyncInventory::class);
```

Blocking is available from the job-class detail UI as well. Jobs already on the queue are dropped when a worker picks them up.

### Retry failed jobs

Failed executions show a **Retry** action. Deck prefers Horizon’s failed-job store, then Laravel’s `failed_jobs` table, then a parameterless re-dispatch when the job class allows it.

### Job progress (long-running jobs)

Report progress from inside a job (stored in cache, shown on the execution detail page while running):

```php
use TorMorten\Deck\Support\JobProgress;

JobProgress::update($this->job->uuid(), 45, 'Imported 450 of 1000 rows');
// or: Deck::updateProgress($this->job->uuid(), 45, '...');
```

Progress is cleared automatically when the job completes or fails.

### Runtime analytics

Job-class detail shows **avg**, **p50**, **p95**, and **failure rate** for the window configured in `DECK_CHART_HOURS` (default 24h).

### Stale job and failure-rate alerts (optional)

```php
// config/deck.php
'alerts' => [
    'enabled' => env('DECK_ALERTS_ENABLED', false),
    'notification' => \App\Notifications\DeckStaleJobsNotification::class,
    'notifiable' => \App\Models\User::class,
    'stale_jobs' => [
        \App\Jobs\SyncInventory::class => ['max_age_hours' => 24],
    ],
    'failure_rate_jobs' => [
        \App\Jobs\SyncInventory::class => [
            'max_failure_rate' => 10,
            'window_hours' => 24,
            'min_samples' => 5,
        ],
    ],
],
```

```php
// routes/console.php
Schedule::command('deck:check-alerts')->hourly();
```

Your notification receives a `Collection` of `TorMorten\Deck\Data\DeckStaleJobAlert` for stale jobs. Failure-rate and unprocessed-queue issues are reported on the console by `deck:check-alerts` (extend with a custom notification if needed).

### Queue administration

On the **Workers** page, clear all **pending** jobs from a Redis queue (with confirmation). Configure via `deck.queue_admin` (`DECK_QUEUE_ADMIN_ENABLED`). Does not remove reserved or in-flight payloads.

Failed executions link to **Horizon** when the job still exists in Horizon's failed-job store.

---

## Configuration

Published `config/deck.php`. Common environment variables:

| Variable | Default | Purpose |
|----------|---------|---------|
| `DECK_DB_CONNECTION` | — | Laravel connection name for `deck_*` tables |
| `DECK_PROJECT` | `APP_NAME` | Stable deployable identifier |
| `DECK_ENVIRONMENT` | `APP_ENV` | Environment label (`production`, `staging`, …) |
| `DECK_RETENTION_DAYS` | `90` | Execution history retention |
| `DECK_CANCEL_CACHE_STORE` | `cache.default` | Cache store for cancel flags |
| `DECK_BLOCK_CACHE_STORE` | same as cancel | Cache store for block flags |
| `DECK_LONG_RUNNING_THRESHOLD_SECONDS` | `300` | Highlight running jobs beyond this duration |
| `DECK_STORE_CONTEXT` | `false` | Persist opt-in `deckContext()` from jobs |
| `DECK_ALERTS_ENABLED` | `false` | Enable stale-job notifications |
| `DECK_UNPROCESSED_QUEUES_ENABLED` | `true` | Detect queues without Horizon workers |
| `DECK_HORIZON_PROMPT` | `true` | Show Horizon vs Deck choice on `/horizon` |
| `DECK_DEFER_SIDE_EFFECTS` | `true` | Defer block side effects during web requests |

Config keys (see file for full list):

| Key | Purpose |
|-----|---------|
| `route_prefix` | Dashboard URL prefix (default: `deck`) |
| `middleware` | Route middleware stack |
| `auth` | Authorization callback (`null` = use Horizon) |
| `database_connection` | Same as `DECK_DB_CONNECTION` |
| `retention_days` | Prune threshold |
| `cancel_ttl_seconds` | Redis TTL for cancel flags |
| `long_running_threshold_seconds` | Long-running highlight |
| `store_context` | Opt-in job context JSON |
| `alerts.*` | Stale-job rules, notification, notifiable |
| `unprocessed_queues.*` | Unprocessed-queue detection |
| `horizon.*` | Prompt, banner, remember choice |
| `poll.*` | Livewire polling intervals (seconds) |
| `tables.*` | Table names if customized |

See [IMPLEMENTATION.md](IMPLEMENTATION.md) for architecture and schema details.

---

## Artisan commands

| Command | Description |
|---------|-------------|
| `deck:install` | Publish config, migrations, and assets |
| `deck:prune` | Delete execution rows older than `retention_days` |
| `deck:check-alerts` | Evaluate stale-job and unprocessed-queue rules |

---

## Relationship to Horizon

```text
┌─────────────────────────────────────────────────────────┐
│  Your Laravel app                                        │
├──────────────────────────┬──────────────────────────────┤
│  Horizon (runtime)       │  Deck (control plane)        │
│  • horizon artisan       │  • Queue event listeners     │
│  • Workers & balancing   │  • DB execution log          │
│  • /horizon dashboard    │  • /deck dashboard           │
│  • Recent/failed in Redis│  • Last run, search, cancel  │
└──────────────────────────┴──────────────────────────────┘
                          Redis queues
```

| Task | Use |
|------|-----|
| Worker health, scaling, supervisors, throughput | **Horizon** |
| Job-class history, search, cancel, block, alerts | **Deck** |

---

## Development

Package tests (from this repository):

```bash
composer test
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## License

MIT © [Tor Morten Jensen](https://github.com/tormjens). See [LICENSE.md](LICENSE.md).
