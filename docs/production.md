# Production

Checklist for deploying Deck to production.

## Dedicated database (recommended)

Deck appends a row on every job start, completion, and failure. On busy queues, use a **separate database**, not your primary application DB.

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

3. Run **only** Deck migrations on that connection (filenames include `deck` after `deck:install`):

```bash
php artisan migrate --database=deck --path=database/migrations/2025_01_01_000000_create_deck_tables.php
# …additional published deck migrations
```

Do **not** run a blanket `php artisan migrate --database=deck` unless that database is dedicated to Deck.

4. Grant the app user read/write on `deck_*` tables only.

If `DECK_DB_CONNECTION` is unset, Deck uses Laravel’s default connection.

## Stable installation identity

```env
DECK_PROJECT=billing-api
DECK_ENVIRONMENT=production
```

Use a stable `DECK_PROJECT` per service. Defaults (`APP_NAME`, `APP_ENV`) are often too generic for multi-app teams.

## Retention and pruning

```env
DECK_RETENTION_DAYS=90
```

```php
// routes/console.php
Schedule::command('deck:prune')->daily();
```

## Secure the dashboard

- Restrict `/deck` to operators — Horizon’s gate or custom `deck.auth`.
- Do not expose Deck publicly without authentication.
- Treat cancel, block, and retry as **privileged** queue operations.

```php
// config/deck.php
'auth' => fn ($request) => $request->user()?->can('view-horizon') ?? false,
```

## Redis cache for cancel and block flags

All Horizon workers must share the same cache store:

```env
DECK_CANCEL_CACHE_STORE=redis
DECK_BLOCK_CACHE_STORE=redis
```

Never use `file` or `array` in multi-server deployments.

## High job volume

- Prefer a dedicated database.
- Keep `DECK_STORE_CONTEXT=false` unless you need opt-in debug fields.
- Lower retention or prune more aggressively when volume is extreme.
- Monitor the Deck DB connection pool.

## Scheduled commands

| Command | Suggested schedule | Purpose |
|---------|-------------------|---------|
| `deck:prune` | Daily | Remove old execution history |
| `deck:check-alerts` | Hourly (when alerts enabled) | Stale jobs and unprocessed queues |
| `deck:report-workers` | Optional | Push workers to Deck Cloud |

## Payloads and sensitive data

Deck does **not** store serialized job payloads by default. Exception messages are truncated; stack traces are capped (`DECK_EXCEPTION_TRACE_BYTES`, default 64 KB).

```env
DECK_STORE_CONTEXT=true
```

Only when jobs implement `Deck\Deck\Contracts\ExposesDeckContext` with non-sensitive scalars — never tokens, passwords, or PII.

## Incident response

1. **Block a job class** — stops new dispatches; optional cancel of in-flight runs.
2. **Cancel running jobs** — cooperative; requires `Cancellable` middleware and `JobCancellation::throwIfCancelled()`.
3. **Retry failed jobs** — from Activity; prefers Horizon’s failed-job store.

Block first, cancel long-running work, retry after fix.

## Unprocessed queue warnings

With Horizon installed, Deck surfaces queues with pending jobs but no workers (`DECK_UNPROCESSED_QUEUES_ENABLED`, default `true`). Review the **Workers** page after deploys or Horizon config changes.
