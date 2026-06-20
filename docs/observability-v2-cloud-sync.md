# Observability v2 — package & Deck Cloud sync spec

Unified specification for **dispatch groups**, **job lifecycle** (queue wait, origin, parent links, timeline), and **ingest API v2** across:

| Repo | Role |
|------|------|
| [`deck/deck`](https://github.com/getdeckapp/deck) agent package | Stamp payloads, local DB, push to Cloud |
| **Deck Cloud** (`deckapp.cloud` SaaS) | Ingest, store, dashboard, remote commands |

**Status:** Draft  
**Target:** Deck package **1.2+** · Deck Cloud **C5** (working title)  
**Related feature specs:** [dispatch-groups.md](dispatch-groups.md) · [job-lifecycle.md](job-lifecycle.md)

---

## Summary

v2 adds optional fields to the existing **`POST /api/v1/ingest/events`** contract. No breaking changes: old agents keep working; new agents send richer events; Cloud stores and displays them when present.

| Capability | Local `/deck` | Cloud `/app` | Ingest field(s) |
|------------|---------------|--------------|-----------------|
| Dispatch group | Activity filter, group panel | Same | `dispatch_group_*` |
| Queue wait | Wait / run stat cards | Same | `dispatched_at`, `wait_ms` |
| Dispatch origin | Origin panel | Same | `dispatch_origin` |
| Parent job | “Dispatched by” link | Same | `parent_job_*` |
| Laravel batch | Link to batch | Same | `batch_id` |
| Lifecycle timeline | Execution timeline | Same | `lifecycle_events` (optional) |
| Cancel group (v2) | Dashboard action | Remote command | `cancel_dispatch_group` command |

---

## Ingest API v2 — event schema

All new fields are **optional**. Cloud MUST accept events without them. Agents SHOULD send them when `deck.dispatch_groups.enabled` / `deck.lifecycle.enabled` are true.

### Batch endpoint (unchanged path)

```
POST /api/v1/ingest/events
Authorization: Bearer {sanctum_token}
Content-Type: application/json
```

```json
{
  "events": [
    {
      "project": "billing-api",
      "environment": "production",
      "job_class": "App\\Jobs\\SyncInvoices",
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "connection": "redis",
      "queue": "default",
      "status": "completed",
      "attempt": 1,
      "tags": ["billing"],
      "started_at": "2026-05-25T14:02:04Z",
      "finished_at": "2026-05-25T14:02:31Z",
      "duration_ms": 27000,
      "dispatched_at": "2026-05-25T14:02:01Z",
      "wait_ms": 3000,
      "dispatch_group_id": "req-7f3a2b1c",
      "dispatch_group_source": "request",
      "batch_id": null,
      "parent_job_uuid": null,
      "parent_job_class": null,
      "dispatch_origin": {
        "type": "http",
        "method": "POST",
        "route": "orders.store",
        "uri": "/api/orders",
        "request_id": "req-7f3a2b1c"
      },
      "lifecycle_events": [
        { "event": "dispatched", "occurred_at": "2026-05-25T14:02:01Z" },
        { "event": "processing", "occurred_at": "2026-05-25T14:02:04Z" },
        { "event": "completed", "occurred_at": "2026-05-25T14:02:31Z", "data": { "duration_ms": 27000 } }
      ],
      "exception_class": null,
      "exception_message": null,
      "exception_trace": null,
      "context": { "invoice_count": 42 }
    }
  ]
}
```

**Response (unchanged):**

```json
{ "accepted": 1, "duplicates": 0 }
```

Single-event endpoints (`POST /api/v1/ingest`, `POST /api/ingest`) accept the same fields on the root object (no `events` wrapper).

### New field reference

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `dispatched_at` | ISO 8601 datetime | No | When job entered queue (or blocked at dispatch) |
| `wait_ms` | integer 0–86400000 | No | `started_at − dispatched_at`; null while unknown |
| `dispatch_group_id` | string max 128 | No | `[a-zA-Z0-9._:-]` recommended; UUID ok |
| `dispatch_group_source` | string | No | `request` \| `lineage` \| `manual` \| `batch` |
| `batch_id` | uuid | No | Laravel `job_batches.id` when present |
| `parent_job_uuid` | uuid | No | Job that was running when this was dispatched |
| `parent_job_class` | string max 500 | No | FQCN; denormalized for list UI |
| `dispatch_origin` | object | No | See origin schema below |
| `lifecycle_events` | array max 20 | No | Append-only milestones; see lifecycle schema |

**Existing fields unchanged.** Semantics for `started_at`, `finished_at`, `duration_ms`, terminal statuses remain as documented in Deck Cloud README.

### `dispatch_origin` schema

```json
{
  "type": "http | artisan | job | manual | schedule | unknown",
  "method": "POST",
  "route": "orders.store",
  "uri": "/api/orders",
  "request_id": "req-7f3a2b1c",
  "command": "orders:sync",
  "schedule": "0 * * * *",
  "parent_uuid": "550e8400-…",
  "parent_class": "App\\Jobs\\ProcessCheckout",
  "label": "nightly-import"
}
```

Cloud validation: `type` required when object present; all other keys optional strings (max lengths per field). No nested objects except none allowed — flat scalar map + `type`.

### `lifecycle_events` schema

Each item:

| Key | Required | Notes |
|-----|----------|-------|
| `event` | Yes | `dispatched` \| `blocked` \| `processing` \| `completed` \| `failed` \| `cancelled` |
| `occurred_at` | Yes | ISO 8601 |
| `data` | No | Scalar map, max 10 keys (e.g. `duration_ms`, `exception_class`) |

Cloud stores in `job_lifecycle_events` table (recommended) or JSON column on `job_executions` (v2.0 shortcut — prefer table).

**Agent send policy:**

- **Running events:** omit `lifecycle_events` or send `dispatched` + `processing` only.
- **Terminal events:** send full milestone list (max 20); agent trims oldest if needed.
- Omit `lifecycle_events` entirely when `deck.lifecycle.events` is false.

### Semantic validation (Cloud)

Extend `ValidatesIngestEventFields` + semantic validator:

| Rule | Error when |
|------|------------|
| `wait_ms` + timestamps | If both `dispatched_at` and `started_at` set, `wait_ms` should equal diff (±1000 ms tolerance) or omit `wait_ms` |
| `dispatched_at` vs `started_at` | `dispatched_at` ≤ `started_at` |
| `parent_job_uuid` | Valid uuid when present |
| `dispatch_group_source` | Enum when `dispatch_group_id` present |
| `lifecycle_events.*.event` | Known enum |
| Running + `wait_ms` | Allowed (partial wait while still running — rare; usually null until terminal) |

Invalid **optional** fields → `422` with field errors (strict). Agents should not send malformed optional data.

---

## Queue payload (agent only, not sent to Cloud directly)

Agent stamps Redis/database queue payloads under one key (see [dispatch-groups.md](dispatch-groups.md)):

```json
{
  "uuid": "…",
  "displayName": "…",
  "job": "…",
  "deck": {
    "dispatch_group": { "id": "…", "source": "request" },
    "dispatched_at": "2026-05-25T14:02:01+00:00",
    "dispatch_origin": { "type": "http", "route": "orders.store" },
    "parent_job": { "uuid": "…", "class": "App\\Jobs\\…" },
    "batch_id": "…"
  }
}
```

Cloud never reads queue payloads — only ingest events from the agent.

---

## Database schema (mirrored)

Agent (`deck_job_executions`) and Cloud (`job_executions`) SHOULD stay aligned.

### Migration: `add_observability_v2_to_job_executions`

| Column | Agent | Cloud |
|--------|-------|-------|
| `dispatched_at` | timestamp nullable | timestamp nullable |
| `wait_ms` | unsigned int nullable | unsigned int nullable |
| `dispatch_group_id` | string(128) nullable | string(128) nullable |
| `dispatch_group_source` | string(16) nullable | string(16) nullable |
| `batch_id` | uuid nullable | uuid nullable |
| `parent_job_uuid` | uuid nullable | uuid nullable |
| `parent_job_class` | string nullable | string nullable |
| `dispatch_origin` | json nullable | json nullable |

**Indexes (both repos):**

```sql
(project, environment, dispatch_group_id, started_at)
(parent_job_uuid)  -- Cloud: (tenant_id, parent_job_uuid)
(batch_id)
(dispatched_at)    -- optional, for slow-wait queries
```

### Migration: `create_job_lifecycle_events` (both repos, phase 2)

| Column | Notes |
|--------|-------|
| `id` | bigint |
| `project` / `tenant_id` | Scope |
| `environment` / `environment_id` | Scope |
| `uuid`, `attempt` | FK logical to execution |
| `event` | string(32) |
| `occurred_at` | timestamp |
| `data` | json nullable |

Prune with execution retention (`deck:prune` / Cloud tenant retention job).

Cloud table names: `job_lifecycle_events` with `tenant_id`, `project_id`, `environment_id`.

---

## Agent package — implementation plan

### 1. Core stamping (shared with local Deck)

| Task | File(s) |
|------|---------|
| `DispatchGroup` service | `src/Dispatch/DispatchGroup.php` |
| `DispatchOrigin` resolver | `src/Dispatch/DispatchOrigin.php` |
| `DispatchLineage` worker scope | `src/Dispatch/DispatchLineage.php` |
| Request middleware | `src/Http/Middleware/AssignDispatchGroup.php` |
| Payload hook | `DeckServiceProvider::registerPayloadStamping()` |
| Worker scope | `src/Queue/DeckCallQueuedHandler.php` |
| Parse payload | `src/Recording/QueuedJobMetadata.php` |
| Persist columns | `src/Recorders/DatabaseJobExecutionRecorder.php` |
| Blocked dispatch | `src/Blocking/BlockedJobExecutionRecorder.php` |
| Compute `wait_ms` | `src/Listeners/RecordJobExecution.php` |
| Lifecycle events | `src/Lifecycle/LifecycleEventRecorder.php` |
| Migration | `database/migrations/2026_05_27_143722_add_observability_v2_to_deck_job_executions.php` |
| Config | `config/deck.php` → `dispatch_groups`, `lifecycle` |

### 2. Cloud outbound (ingest)

| Task | File(s) |
|------|---------|
| Extend live ingest payload | `src/Cloud/Events/JobExecutionIngestPayload.php` |
| Extend backfill payload | `src/Cloud/Events/CloudExecutionBackfillPayload.php` |
| Include new fields on `JobExecutionRecord` / model reads | `src/Data/JobExecutionRecord.php`, `src/Models/JobExecution.php` |
| Gate optional fields behind config | Same as local feature flags |
| Tests | `tests/Feature/CloudEventsTest.php`, new `CloudObservabilityV2Test.php` |

**`JobExecutionIngestPayload` additions (pseudocode):**

```php
if ($record->dispatchedAt) {
    $payload['dispatched_at'] = $record->dispatchedAt->utc()->toIso8601String();
}
if ($record->waitMs !== null) {
    $payload['wait_ms'] = $record->waitMs;
}
// dispatch_group_*, batch_id, parent_*, dispatch_origin, lifecycle_events
```

Send on **running** events too (Cloud already accepts running) — enables wait/progress on Cloud before completion.

### 3. Local UI (package)

See [dispatch-groups.md](dispatch-groups.md) and [job-lifecycle.md](job-lifecycle.md) UI sections. Cloud parity listed below.

### 4. Agent commands (consume from Cloud, v2)

New command type in agent poller:

| Cloud → agent `type` | Agent action |
|----------------------|--------------|
| `cancel_dispatch_group` | `Deck::cancelDispatchGroup($payload['dispatch_group_id'], force: …)` |

Ack payload unchanged (`applied` / `failed` + message).

### 5. Backfill

`deck:cloud-backfill` MUST include v2 fields from local DB so historical data gains groups/lifecycle after upgrade.

---

## Deck Cloud — implementation plan

Repo: `/Users/tormorten/SynologyDrive/Prosjekter/TMJ Media/Apps/deck` (also published as hosted SaaS).

### 1. Ingest layer (deploy first — forward compatible)

| Task | File(s) |
|------|---------|
| Validation rules | `app/Http/Requests/Api/V1/Concerns/ValidatesIngestEventFields.php` |
| Semantic rules | Same trait — `validateIngestEventSemantics()` |
| Upsert columns | `app/Actions/ProcessIngestEvent.php` |
| Batch processor | `app/Actions/ProcessIngestEventsBatch.php` (pass-through) |
| Model fillable / casts | `app/Models/JobExecution.php` |
| Migration | `database/migrations/xxxx_add_observability_v2_to_job_executions.php` |
| Lifecycle events | `app/Models/JobLifecycleEvent.php`, `ProcessIngestLifecycleEvents.php` |
| Tests | `tests/Feature/Api/IngestTest.php` — new cases for optional fields, backward compat |

**Rollout rule:** Cloud production MUST ship ingest acceptance **before** or **with** agent 1.2 that sends new fields. Older agents: unchanged payloads. New agents + old Cloud: unknown fields rejected unless Cloud deployed first — **deploy Cloud first**.

### 2. Dashboard UI

| Surface | Cloud path | Work |
|---------|------------|------|
| Activity filters | `/app/activity` | `ActivityFilters.vue` — group id, batch id |
| Activity index | `ActivityController` | Query `dispatch_group_id`, expose filter options |
| Execution show | `/app/activity/{uuid}/{attempt}` | Origin panel, wait/run stats, timeline, parent/child links, group siblings |
| Global search | `/app/search` | Match `dispatch_group_id`, `parent_job_uuid` |
| Jobs class stats | `/app/jobs` | Optional p95 wait (when enough data) |
| Overview | `/app` | Optional slow-wait card (phase 2) |

Mirror copy/labels from package Blade components (`dispatch group`, not campaign).

### 3. Real-time

`JobExecutionIngested` broadcast payload — add optional `dispatch_group_id` so Activity live refresh can group-related rows.

Channel: existing `tenant.{id}.executions`.

### 4. Remote commands (issue side)

| Task | File(s) |
|------|---------|
| Command enum | `app/Enums/AgentCommandType.php` |
| Issue from UI | Activity group panel → `AgentCommandController` / action class |
| Validation | `dispatch_group_id` required in command payload |

Agent package implements handler (see above).

### 5. Documentation

| Doc | Action |
|-----|--------|
| `README.md` § Ingest API | Add v2 field table |
| `docs/ingest-v2-spec.md` | Copy of this spec (Cloud repo canonical mirror) |
| Installation guide | Note agent 1.2+ for group/lifecycle UI |

### 6. Admin / quota

New fields increase payload size marginally. No quota change required. Monitor `lifecycle_events` array length (cap 20 server-side).

---

## Coordinated release plan

```text
Phase 0 — Spec & agreement (this document)
Phase 1 — Cloud ingest accepts v2 optional fields + DB migration
Phase 2 — Agent package stamping + local DB + ingest send + tests
Phase 3 — Local /deck UI (read-only groups, lifecycle)
Phase 4 — Cloud /app UI parity (read-only)
Phase 5 — cancel_dispatch_group command (Cloud issue + agent handle)
Phase 6 — lifecycle_events table + timeline UI both sides
Phase 7 — deck:cloud-backfill docs + slow-wait analytics
```

| Milestone | Agent version | Cloud phase | Compatible? |
|-----------|---------------|-------------|-------------|
| Baseline | 1.1.x | C4 | Today |
| Cloud-ready ingest | 1.1.x | C5a ingest only | Old agent OK |
| Full v2 agent | 1.2.x | C5a | Agent sends; Cloud stores |
| Full v2 UI | 1.2.x | C5b UI | — |
| Group cancel | 1.2.x | C5c commands | Both required |

---

## Idempotency & updates

Unchanged: one row per `(tenant_id, uuid, attempt)` on Cloud; `(uuid, attempt)` on agent.

**v2 field update policy on re-ingest:**

| Field | On duplicate status | On status transition |
|-------|---------------------|----------------------|
| `dispatched_at`, origin, group, parent | Set on first insert; update if previously null | Preserve first non-null |
| `wait_ms` | Update when transitioning to terminal | Latest wins |
| `lifecycle_events` | Merge by `(event, occurred_at)` dedupe; cap 20 | Append new |

Cloud `ProcessIngestEvent` `update:` array must include all v2 columns.

---

## Feature flags (aligned)

| Agent env | Cloud config | Effect |
|-----------|--------------|--------|
| `DECK_DISPATCH_GROUPS_ENABLED=false` | — | Agent omits group fields |
| `DECK_LIFECYCLE_ENABLED=false` | — | Agent omits wait/origin/parent |
| — | `CLOUD_INGEST_ACCEPT_V2_FIELDS=true` (default true) | Reject vs ignore unknown keys (always accept when true) |

Cloud does not need separate feature flags for storage — if field arrives, store it.

---

## Testing matrix

| Case | Agent test | Cloud test |
|------|------------|------------|
| Event without v2 fields | ✓ still ingests | ✓ backward compat |
| Full v2 completed event | ✓ payload shape | ✓ upsert all columns |
| Running event with `dispatched_at` | ✓ | ✓ no `finished_at` |
| Invalid `dispatch_group_source` | — | ✓ 422 |
| `lifecycle_events` > 20 | ✓ agent trims | ✓ 422 |
| Backfill includes v2 | ✓ CloudBackfillCommand | ✓ acceptance |
| Group filter Activity | Feature | Feature |
| `cancel_dispatch_group` round trip | Feature | Feature |

---

## Open questions

1. **Cloud `context` vs `dispatch_origin`** — keep separate; do not overload `context` scalar map.
2. **Lifecycle separate endpoint** — defer; nested `lifecycle_events` on batch ingest is enough for v2.
3. **Cross-tenant group IDs** — groups are not unique globally; always scope UI queries by `(tenant, project, environment, dispatch_group_id)`.
4. **Search index size** — add `dispatch_group_id` to `ExecutionSearch` indexed fields on Cloud.

---

## Quick reference — files to touch

### Agent package (`deck/deck`)

```
config/deck.php
src/Dispatch/*
src/Http/Middleware/AssignDispatchGroup.php
src/DeckServiceProvider.php
src/Bus/DeckDispatcher.php
src/Queue/DeckCallQueuedHandler.php
src/Recording/QueuedJobMetadata.php
src/Listeners/RecordJobExecution.php
src/Recorders/DatabaseJobExecutionRecorder.php
src/Cloud/Events/JobExecutionIngestPayload.php
src/Cloud/Events/CloudExecutionBackfillPayload.php
src/Cloud/Commands/* (cancel_dispatch_group handler)
database/migrations/*_add_observability_v2_*.php
tests/Feature/CloudEventsTest.php
tests/Feature/DispatchGroup*.php
tests/Feature/JobLifecycle*.php
```

### Deck Cloud

```
app/Http/Requests/Api/V1/Concerns/ValidatesIngestEventFields.php
app/Actions/ProcessIngestEvent.php
app/Models/JobExecution.php
app/Models/JobLifecycleEvent.php
app/Enums/AgentCommandType.php
database/migrations/*_add_observability_v2_*
resources/js/Pages/Activity/*
resources/js/Components/ActivityFilters.vue
tests/Feature/Api/IngestTest.php
README.md (Ingest API section)
docs/ingest-v2-spec.md
```

---

## Summary

Observability v2 is one **additive ingest contract** shared by the agent and Deck Cloud. The agent stamps queue payloads once, persists locally, and sends the same fields Cloud stores. Deploy Cloud ingest first, then ship agent 1.2, then UI on both sides, then `cancel_dispatch_group`. Dispatch groups and job lifecycle are product features; this document is the **sync boundary** between repos.
