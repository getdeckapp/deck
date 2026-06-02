# Production

Checklist for deploying Deck to production.

## Dedicated database (recommended)

Deck appends a row on every job start, completion, and failure. On busy queues, use a **separate database**, not your primary application DB.

Deck provides its own `deck` database connection. Out of the box it clones your application's default connection, so nothing is required to get started. Deck's migrations always target this connection, so a plain `php artisan migrate` puts the `deck_*` tables in the right place.

### Point Deck at a separate database with env vars (recommended)

You usually do not need to touch `config/database.php`. Set any of the `DECK_DB_*` variables and Deck overlays them onto its cloned default connection. Anything you leave unset falls back to the default connection's value:

```env
DECK_DB_DATABASE=deck
DECK_DB_USERNAME=deck
DECK_DB_PASSWORD=secret
# Optional — fall back to the default connection when unset:
# DECK_DB_DRIVER=mysql
# DECK_DB_HOST=127.0.0.1
# DECK_DB_PORT=3306
# DECK_DB_UNIX_SOCKET=
# DECK_DB_CHARSET=utf8mb4
# DECK_DB_PREFIX=
# DECK_DB_SCHEMA=public   # pgsql
```

Then run the Deck migrations — they target Deck's connection internally:

```bash
php artisan migrate
```

Grant the app user read/write on `deck_*` tables only.

### Use an existing connection instead

If you already maintain a dedicated connection in `config/database.php`, point Deck at it by name. Deck never overrides a connection that is already defined:

```env
DECK_DB_CONNECTION=deck
```

If you do not define a `deck` connection and set no `DECK_DB_*` overrides, Deck provisions one by cloning Laravel's default connection.

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

To run every enabled Deck maintenance command in one pass — handy for manual runs or a single cron entry — use `deck:run-scheduled`. It runs `deck:prune` and, when their features are enabled, `deck:check-alerts`, `deck:report-workers`, and `deck:poll-commands`, skipping the rest.

```bash
php artisan deck:run-scheduled
```

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
