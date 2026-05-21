# Deck Cloud

**One operations dashboard for every Laravel app — without replacing Horizon or moving your queues.**

Horizon and Redis stay on your infrastructure. The `deck/deck` package is the **agent**; [Deck Cloud](https://deckapp.cloud) is where operators manage many services.

## What you get

| On each app (self-hosted) | On Deck Cloud (hosted) |
|---------------------------|-------------------------|
| `/deck` for local history and incidents | **One URL** for every project and environment |
| Horizon for workers and throughput | Worker snapshots and queue workload |
| Cancel/block flags in **your** Redis | **Remote commands** — cancel, block, drain |
| Per-app alerts and search | Cross-app signals (roadmap) |

## How it works

```text
  billing-api (prod) ──┐
  billing-api (staging)├──► Deck Cloud  ◄── you
  notifications (prod)─┘         ▲
                                 │ HTTPS (API key)
  Each app: deck package ────────┘   workers · commands · events
  Each app: Horizon / Redis (unchanged)
```

1. Install `deck/deck` in each Laravel app.
2. Set stable `DECK_PROJECT` and `DECK_ENVIRONMENT` per deployable.
3. Add a **Deck Cloud API key** — the agent enables automatically.
4. Open Cloud, filter by project and environment, act on queues from one place.

The in-app dashboard shows a **Cloud connection** indicator when linked. Remote commands use the same primitives as locally: cooperative cancel, block class, cancel pending, and more.

## Connect an app

```env
DECK_PROJECT=billing-api
DECK_ENVIRONMENT=production
DECK_API_KEY=your-agent-token
```

That is enough for worker ingest, command polling, and execution event sync.

| Variable | Default | Purpose |
|----------|---------|---------|
| `DECK_API_KEY` | — | Agent token; enables Cloud when set |
| `DECK_CLOUD_URL` | `http://deck.test` (local) / `https://deckapp.cloud` | Cloud base URL |
| `DECK_CLOUD_ENABLED` | auto | Set `false` to disable while keeping the key |
| `DECK_CLOUD_WORKERS_ENABLED` | `true` | Push worker snapshots |
| `DECK_CLOUD_WORKERS_INTERVAL` | `30` | Sync throttle (seconds) |
| `DECK_CLOUD_COMMANDS_ENABLED` | `true` | Pull remote commands |
| `DECK_CLOUD_EVENTS_ENABLED` | `true` | Push execution events |
| `DECK_CLOUD_EVENTS_BATCH_SIZE` | `25` | Event batch size (1–100) |
| `DECK_CLOUD_PROMO` | `true` | Sidebar link to deckapp.cloud when Cloud is off |

Set `DECK_CLOUD_ENABLED=false` to turn the agent off without removing the API key.

## Agent API (summary)

When enabled with an API key:

1. **POST** `/api/v1/ingest/workers` — worker snapshots + optional queue workload (max 100 workers per request).
2. **GET** `/api/v1/agent/commands` — pull commands for this project + environment.
3. **POST** `/api/v1/agent/commands/ack` — acknowledge results.
4. **POST** `/api/v1/ingest/events` — batched execution events (max 100 per request).

Sync runs on Horizon `MasterSupervisorLooped`, throttled `Queue::looping`, and `deck:report-workers`.

Remote commands map to `requestCancelExecution`, `forceCancelExecution`, `cancelPending`, `blockClass`, `unblockClass`, and `cancelAllRunningForClass`.
Learn more: **[deckapp.cloud](https://deckapp.cloud?utm_source=deck-oss&utm_medium=docs)**
