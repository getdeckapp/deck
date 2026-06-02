# Usage

## Automatic recording

After installation, Deck listens to queue events and records starts, completions, and failures. No code changes required for basic history.

## Dashboard routes

| Route | Purpose |
|-------|---------|
| `/deck` | Overview, charts, recent failures, queue health |
| `/deck/classes` | Per-class stats and history |
| `/deck/activity` | Searchable execution log |
| `/deck/workers` | Horizon snapshot and unprocessed queues |

Prefix is configurable via `deck.route_prefix`.

## Cooperative cancellation

```php
use Deck\Deck\Middleware\Cancellable;
use Deck\Deck\Cancellation\JobCancellation;

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

```php
use Deck\Deck\Facades\Deck;

Deck::cancel($jobUuid);
```

Cancellation is **cooperative** — Deck does not force-kill PHP workers. Pending cancel on Redis queues is best effort.

## Block a job class

```php
Deck::blockClass(\App\Jobs\SyncInventory::class, until: now()->addHour(), reason: 'Upstream API outage');
Deck::unblockClass(\App\Jobs\SyncInventory::class);
```

Blocked jobs are intercepted at dispatch (status `blocked`). Available from the job-class UI.

## Retry failed jobs

**Retry** on failed executions uses Horizon’s failed-job store when available, then Laravel `failed_jobs`, then parameterless re-dispatch when allowed.

## Job progress

```php
use Deck\Deck\Recording\JobProgress;

JobProgress::update($this->job->uuid(), 45, 'Imported 450 of 1000 rows');
```

Shown on the execution detail page while running; cleared on complete/fail.

## Runtime analytics

Job-class detail shows **avg**, **p50**, **p95**, and **failure rate** for `DECK_CHART_HOURS` (default 24h).

## Alerts (optional)

```php
// config/deck.php
'alerts' => [
    'enabled' => env('DECK_ALERTS_ENABLED', false),
    'notification' => \App\Notifications\DeckStaleJobsNotification::class,
    'notifiable' => \App\Models\User::class,
    'stale_jobs' => [
        \App\Jobs\SyncInventory::class => ['max_age_hours' => 24],
    ],
],
```

```php
Schedule::command('deck:check-alerts')->hourly();
```

## Queue administration

On **Workers**, clear all **pending** jobs from a Redis queue (with confirmation). Configure via `DECK_QUEUE_ADMIN_ENABLED`. Does not remove reserved or in-flight jobs.

Failed executions link to **Horizon** when the job still exists in Horizon’s failed-job store.

## Artisan commands

| Command | Description |
|---------|-------------|
| `deck:install` | Publish config, migrations, and assets |
| `deck:prune` | Delete execution rows older than `retention_days` |
| `deck:check-alerts` | Evaluate stale-job and unprocessed-queue rules |
| `deck:report-workers` | Push worker snapshots to Deck Cloud (no-op when disabled) |
| `deck:run-scheduled` | Run every enabled Deck maintenance command in one pass |
