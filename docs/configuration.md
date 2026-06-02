# Configuration

Published `config/deck.php`. Common environment variables:

| Variable | Default | Purpose |
|----------|---------|---------|
| `DECK_DB_CONNECTION` | `deck` | Laravel connection for `deck_*` tables (auto-provisioned from the default connection when undefined) |
| `DECK_DB_DRIVER` | default connection | Driver for the auto-provisioned `deck` connection |
| `DECK_DB_HOST` | default connection | Host override |
| `DECK_DB_PORT` | default connection | Port override |
| `DECK_DB_DATABASE` | default connection | Database name override |
| `DECK_DB_USERNAME` | default connection | Username override |
| `DECK_DB_PASSWORD` | default connection | Password override |
| `DECK_DB_UNIX_SOCKET` | default connection | Unix socket override |
| `DECK_DB_CHARSET` | default connection | Charset override |
| `DECK_DB_PREFIX` | default connection | Table prefix override |
| `DECK_DB_SCHEMA` | default connection | Schema override (pgsql) |
| `DECK_PROJECT` | `APP_NAME` | Stable deployable identifier |
| `DECK_ENVIRONMENT` | `APP_ENV` | Environment label |
| `DECK_RETENTION_DAYS` | `90` | Execution history retention |
| `DECK_CANCEL_CACHE_STORE` | `cache.default` | Cache store for cancel flags |
| `DECK_BLOCK_CACHE_STORE` | same as cancel | Cache store for block flags |
| `DECK_LONG_RUNNING_THRESHOLD_SECONDS` | `300` | Highlight long-running jobs |
| `DECK_STORE_CONTEXT` | `false` | Persist opt-in `deckContext()` |
| `DECK_ALERTS_ENABLED` | `false` | Stale-job notifications |
| `DECK_UNPROCESSED_QUEUES_ENABLED` | `true` | Detect queues without workers |
| `DECK_HORIZON_PROMPT` | `true` | Horizon vs Deck choice on `/horizon` |
| `DECK_DEFER_SIDE_EFFECTS` | `true` | Defer block side effects during web requests |
| `DECK_QUEUE_ADMIN_ENABLED` | `true` | Clear-queue actions on Workers page |
| `DECK_CHART_HOURS` | `24` | Analytics window for charts |

## Config keys (published file)

| Key | Purpose |
|-----|---------|
| `route_prefix` | Dashboard URL prefix (default: `deck`) |
| `middleware` | Route middleware stack |
| `auth` | Authorization callback (`null` = use Horizon when bootstrapped) |
| `database_connection` | Same as `DECK_DB_CONNECTION` |
| `database` | Field-by-field overrides for the auto-provisioned `deck` connection (`DECK_DB_*`) |
| `retention_days` | Prune threshold |
| `cancel_ttl_seconds` | Redis TTL for cancel flags |
| `long_running_threshold_seconds` | Long-running highlight |
| `store_context` | Opt-in job context JSON |
| `alerts.*` | Stale-job rules, notification, notifiable |
| `unprocessed_queues.*` | Unprocessed-queue detection |
| `horizon.*` | Prompt, banner, remember choice |
| `poll.*` | Livewire polling intervals (seconds) |
| `tables.*` | Table names if customized |
| `cloud.*` | Deck Cloud agent — see [Deck Cloud](deck-cloud.md) |
