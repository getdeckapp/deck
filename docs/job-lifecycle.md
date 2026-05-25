# Job lifecycle — design spec

Queue wait time, dispatch origin, parent job links, and an execution timeline. Complements [dispatch groups](dispatch-groups.md) (grouping) with **timing** and **causality** (why, when, from where).

**Status:** Draft spec (not implemented)  
**Target:** Deck 1.2+ (after or alongside dispatch groups — shared payload stamping)  
**Depends on:** Partial overlap with [dispatch groups](dispatch-groups.md) (`Queue::createPayloadUsing`, worker scope, request middleware)  
**Cloud sync:** [observability-v2-cloud-sync.md](observability-v2-cloud-sync.md)

---

## Problem

Deck records executions when a worker **starts** a job. That answers “what ran and how long,” but not:

| Gap | Symptom |
|-----|---------|
| No **queue wait** | “Job took 30s” — was it slow code or 29s in Redis? |
| No **dispatch origin** | “Who dispatched this?” — grep logs or guess |
| No **parent link** | “Which job spawned this?” — dispatch groups show siblings, not tree edges |
| Flat **status row** | Hard to see the sequence: dispatched → waiting → running → progress → done |

Horizon shows recent Redis jobs; Deck should own the **durable lifecycle narrative**.

---

## Goals

1. **Record `dispatched_at`** at queue push time (via payload stamp).
2. **Compute `wait_ms`** when processing starts (`started_at - dispatched_at`).
3. **Stamp dispatch origin** (HTTP route, Artisan command, parent job, manual).
4. **Stamp `parent_job_uuid`** when dispatching from a worker.
5. **Show a lifecycle timeline** on execution detail (and key metrics on class stats).
6. **Reuse dispatch groups infrastructure** — one payload hook, one middleware stack.

## Non-goals (v1)

- Full OpenTelemetry integration (optional `traceparent` passthrough only).
- Storing full job payloads or constructor arguments.
- Release/backoff events (v2 — see §Future).
- Separate “pending execution” rows for jobs never picked up (v2 alert only).
- Replacing Horizon’s live “Recent Jobs” view.

---

## Relationship to dispatch groups

| Concern | Dispatch groups | Job lifecycle |
|---------|-----------------|---------------|
| Grouping | Same request / lineage / manual | — |
| Tree edge | — | `parent_job_uuid` |
| Timing | — | `dispatched_at`, `wait_ms` |
| Causality label | Group source | `dispatch_origin` (richer) |

**Shared implementation:** extend the same `Queue::createPayloadUsing` callback and worker scope from dispatch groups. Implement together or lifecycle immediately after groups.

Payload namespace (single object recommended):

```json
{
  "deck": {
    "dispatch_group": { "id": "…", "source": "request" },
    "dispatched_at": "2025-05-25T14:02:01+00:00",
    "dispatch_origin": { "type": "http", "route": "orders.store", "method": "POST", "uri": "/api/orders" },
    "parent_job": { "uuid": "…", "class": "App\\Jobs\\ProcessCheckout" }
  }
}
```

Use one `deck` key in the queue payload to avoid collisions and simplify parsing.

---

## Terminology

| Term | Meaning |
|------|---------|
| **Dispatched at** | When the job was pushed to the queue (or intercepted as blocked). |
| **Started at** | When a worker began processing (existing `started_at`). |
| **Wait time** | Time in queue before processing (`wait_ms`). |
| **Run time** | Worker execution time (existing `duration_ms`). |
| **Dispatch origin** | What caused the dispatch (HTTP, Artisan, parent job, …). |
| **Parent job** | The job that was running when this job was dispatched. |
| **Lifecycle event** | Append-only timeline entry (dispatched, running, completed, …). |

---

## Data model

### Migration: `add_lifecycle_fields_to_deck_job_executions`

| Column | Type | Notes |
|--------|------|-------|
| `dispatched_at` | `timestamp` nullable | From payload at first record |
| `wait_ms` | `unsignedInteger` nullable | Set when status → running |
| `dispatch_origin` | `json` nullable | See schema below |
| `parent_job_uuid` | `uuid` nullable | Indexed |
| `parent_job_class` | `string` nullable | Denormalized for list UI |

Indexes:

```php
$table->index(['project', 'environment', 'parent_job_uuid'], 'deck_exec_parent_idx');
$table->index(['project', 'environment', 'dispatched_at'], 'deck_exec_dispatched_idx');
```

`wait_ms` denormalized for charts; recomputable from timestamps.

### Migration: `create_deck_job_lifecycle_events_table` (v1 optional, v1.5 recommended)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | |
| `project` | string | |
| `environment` | string | |
| `uuid` | uuid | Job UUID |
| `attempt` | tinyint | |
| `event` | string(32) | See events below |
| `occurred_at` | timestamp | |
| `data` | json nullable | Event-specific payload |
| `created_at` | timestamp | |

Index: `(uuid, attempt, occurred_at)`.

Prune with `deck:prune` using same retention as executions.

**v1 shortcut:** derive timeline from execution columns only; add events table when progress/release events need history.

---

## Dispatch origin schema

```php
// dispatch_origin JSON shape — all fields optional except type
[
    'type' => 'http' | 'artisan' | 'job' | 'manual' | 'schedule' | 'unknown',
    // http
    'method' => 'POST',
    'route' => 'orders.store',       // route name when available
    'uri' => '/api/orders',          // truncated to 255 chars
    'request_id' => '…',             // same as X-Request-Id when present
    // artisan
    'command' => 'orders:sync',      // argv[0] or signature
    'schedule' => '0 * * * *',       // when dispatched by scheduler (if detectable)
    // job (parent — duplicate of parent_job_* columns for self-contained JSON)
    'parent_uuid' => '…',
    'parent_class' => 'App\\Jobs\\…',
    // manual
    'label' => 'nightly-import',     // from DispatchGroup::using() optional label
]
```

### Origin resolution (at dispatch)

| Context | `type` | Source |
|---------|--------|--------|
| HTTP request | `http` | `Request::method()`, `route()?->getName()`, `Request::path()` |
| Artisan / console | `artisan` | `Artisan::running()` → command name; detect schedule via `Schedule` event if feasible |
| Inside worker | `job` | Active worker scope: parent UUID + class from current job |
| `DispatchGroup::using('label')` without HTTP | `manual` | Label from scope |
| Else | `unknown` | Omit or minimal |

Capture origin in `DispatchOrigin::resolveForDispatch()` parallel to `DispatchGroup::resolveForDispatch()`.

---

## Parent job stamping

When `DispatchGroup::scopeFromJob()` (or dedicated `DispatchLineage::scopeFromJob()`) runs the worker:

```php
DispatchLineage::current(); // DispatchLineageState { uuid, class }
```

At payload creation inside worker scope:

```php
'parent_job' => [
    'uuid' => $lineage->uuid,
    'class' => $lineage->class,
],
'dispatch_origin' => ['type' => 'job', 'parent_uuid' => …, 'parent_class' => …],
```

Top-level HTTP dispatch: `parent_job` absent, origin `type: http`.

**UI:** link parent UUID to execution show (same attempt 1 or latest attempt). Section **“Dispatched by”** with class basename + link.

**Tree view (v1.5):** on execution detail, list **child jobs** (`WHERE parent_job_uuid = :uuid`). Full tree deferred to dispatch group page.

---

## Queue wait timing

### At dispatch (`Queue::createPayloadUsing`)

```php
$payload['deck']['dispatched_at'] = now()->utc()->toIso8601String();
```

Also read Laravel payload `delay` / `available_at` if present — store in `deck.available_at` for delayed jobs.

### Blocked at dispatch

`BlockedJobExecutionRecorder` sets `dispatched_at = now()`, `wait_ms = 0`, origin from current context, status `blocked`. No worker run.

### At processing (`RecordJobExecution::handleProcessing`)

```php
$dispatchedAt = QueuedJobMetadata::dispatchedAt($job); // parse from payload deck.dispatched_at
$startedAt = now();
$waitMs = $dispatchedAt ? max(0, $dispatchedAt->diffInMilliseconds($startedAt)) : null;
```

Persist `dispatched_at`, `wait_ms` on first running record.

### Delayed jobs

If `available_at` > `dispatched_at`, UI shows:

- **Scheduled for** — available_at  
- **Wait (after available)** — started_at - available_at  
- **Total wait** — started_at - dispatched_at  

Store `available_at` in `dispatch_origin` or dedicated nullable column if common.

### Sync / deferred drivers

Best-effort: `dispatched_at ≈ started_at`, `wait_ms = 0` or null. Document as low signal.

---

## Lifecycle events

### Event types (v1)

| Event | When recorded | `data` |
|-------|---------------|--------|
| `dispatched` | Payload pushed (optional: dispatch listener) | origin, group id |
| `blocked` | Block intercept | reason if any |
| `processing` | JobProcessing | worker name optional v2 |
| `completed` | JobProcessed | duration_ms |
| `failed` | JobFailed | exception class |
| `cancelled` | Cancel terminal | cooperative vs force |

Progress stays in cache (`JobProgress`); timeline shows **latest progress** from cache while running, not every progress event in v1 (avoid event flood).

### Recording

```php
LifecycleEventRecorder::record(
    uuid: $metadata->uuid,
    attempt: $metadata->attempt,
    event: LifecycleEvent::Processing,
    occurredAt: $startedAt,
    data: [...],
);
```

Called from `RecordJobExecution` and `BlockedJobExecutionRecorder`.

### v2 events

- `released` — `$job->release($delay)` hook via custom middleware or queue listener  
- `retry_scheduled` — Laravel retry after failure  
- `progress` — optional sampled progress milestones (0, 25, 50, 75, 100)

---

## `QueuedJobMetadata` extensions

```php
public readonly ?Carbon $dispatchedAt;
public readonly ?Carbon $availableAt;
public readonly ?array $dispatchOrigin;
public readonly ?string $parentJobUuid;
public readonly ?string $parentJobClass;
public readonly ?array $deckPayload; // raw deck key for forward compat
```

Parse from `$job->payload()['deck']` with fallback for legacy unstamped jobs.

---

## Configuration

```php
// config/deck.php
'lifecycle' => [
    'enabled' => (bool) env('DECK_LIFECYCLE_ENABLED', true),

    // Record append-only lifecycle events table
    'events' => (bool) env('DECK_LIFECYCLE_EVENTS', true),

    // Capture HTTP route/method on dispatch
    'origin_http' => (bool) env('DECK_LIFECYCLE_ORIGIN_HTTP', true),

    // Capture Artisan command name
    'origin_artisan' => (bool) env('DECK_LIFECYCLE_ORIGIN_ARTISAN', true),

    // Stamp parent job when dispatching from worker
    'parent_job' => (bool) env('DECK_LIFECYCLE_PARENT_JOB', true),

    // Include wait_ms in class-level analytics
    'wait_analytics' => (bool) env('DECK_LIFECYCLE_WAIT_ANALYTICS', true),

    // Alert when wait_ms exceeds threshold (optional, uses deck.alerts)
    'slow_queue_wait_seconds' => (int) env('DECK_LIFECYCLE_SLOW_QUEUE_WAIT', 300),
],
```

When `lifecycle.enabled` is false, skip stamping and columns stay null.

---

## Analytics

### Class detail (when `wait_analytics`)

Extend existing p50/p95 block:

| Metric | Source |
|--------|--------|
| Avg / p95 **wait** | `wait_ms` on completed executions, 24h window |
| Avg / p95 **run** | existing `duration_ms` |

Helps classify “queue congestion” vs “slow job.”

### Dashboard (optional v1.5)

Small card: **“Slow queue wait (24h)”** — executions where `wait_ms > threshold`.

---

## UI

### Execution detail — stat cards

Replace or extend current row:

| Label | Value |
|-------|-------|
| Wait | `FormatDuration::format($wait_ms)` or `—` |
| Run | existing duration |
| Total | wait + run (terminal only) |

### Execution detail — origin panel

```
Triggered by    POST orders.store  /api/orders
Dispatch group  req_abc123  (4 related jobs →)
Dispatched by   ProcessCheckoutJob  (link if parent)
Dispatched      May 25, 14:02:01
Started         May 25, 14:02:04  (+3s wait)
Finished        May 25, 14:02:31
```

### Execution detail — timeline component

`<x-deck::lifecycle-timeline :events="$timeline" :progress="$progress" />`

Vertical timeline, newest last or first — match Activity chronological convention (newest first in list, timeline oldest-at-top).

States:

- Gray — dispatched / waiting  
- Blue — processing / progress  
- Green — completed  
- Red — failed  
- Amber — cancelled / blocked  

Live running: merge cache progress into open processing step (poll with existing `poll-container`).

### Activity table (optional column)

For `status === running`, show thin progress bar or wait time if `dispatched_at` known and not yet started — usually N/A once in table.

### Child jobs section

If any executions have `parent_job_uuid = this.uuid`:

```
Child jobs (3)
  SendReceiptJob      completed   2s
  SendWebhookJob      running     …
```

Link each to execution show.

---

## Deck Cloud ingest

See **[observability-v2-cloud-sync.md](observability-v2-cloud-sync.md)** for the unified agent ↔ Cloud contract. Lifecycle fields are optional on `POST /api/v1/ingest/events`; Cloud stores them on `job_executions` (+ optional `job_lifecycle_events`).

---

## Public API

No required app-facing API for v1 (automatic).

Optional read helpers:

```php
JobExecution::query()->childrenOf(string $parentUuid);
JobExecution::query()->withSlowWait(int $seconds);
```

---

## Implementation phases

### Phase 1 — Payload + columns (with dispatch groups)

- [ ] Unified `deck` payload object in `createPayloadUsing`
- [ ] `DispatchOrigin` resolver + request/console middleware hooks
- [ ] `DispatchLineage` worker scope (may merge with `DispatchGroup` scope)
- [ ] Migration: lifecycle columns on executions
- [ ] `QueuedJobMetadata` + recorder + blocked recorder
- [ ] Compute `wait_ms` in `handleProcessing`
- [ ] Tests

### Phase 2 — Timeline UI

- [ ] `deck_job_lifecycle_events` migration + recorder
- [ ] `LifecycleTimeline` presenter (events + progress + execution columns)
- [ ] Execution show: origin panel, stat cards, timeline
- [ ] Parent / child links

### Phase 3 — Analytics & alerts

- [ ] Class p95 wait metrics
- [ ] Optional slow-wait alert rule
- [ ] Cloud ingest fields

### Phase 4 — Future (separate spec)

- [ ] Release/backoff events  
- [ ] Stuck-in-queue detection (dispatched, never started)  
- [ ] `traceparent` passthrough  
- [ ] Full tree on dispatch group page  

---

## Test plan (Pest)

**Unit**

- Parse `deck` payload into metadata (missing keys → null)
- `wait_ms` calculation with fixed clocks
- Origin resolver for HTTP, Artisan, job scope
- Parent stamped only inside worker scope

**Feature**

- Dispatch 3 jobs in HTTP test → origin `type: http`, same route
- Job dispatches child → child has `parent_job_uuid`, origin `type: job`
- Delayed job → `available_at` reflected in UI data
- Blocked dispatch → `dispatched_at` set, `wait_ms` 0, event `blocked`
- Timeline contains dispatched → processing → completed in order
- Prune deletes lifecycle events with executions

---

## Open questions

1. **Single migration with dispatch groups?** Recommend one `add_deck_payload_fields_to_executions` migration for group + lifecycle columns.
2. **Events table in v1?** Can ship column-only timeline first; events table before progress history.
3. **Scheduler detection** — `origin.type = schedule` requires listening to `Illuminate\Console\Events\ScheduledTaskStarting` and scoping dispatches — defer to v1.5.
4. **Horizon supervisor name** — stamp at `JobProcessing` from Horizon API if cheap; defer v2.

---

## Summary

Job lifecycle adds **when** (dispatched, wait, run), **why** (dispatch origin), and **from whom** (parent job) on top of dispatch groups’ **what else** (siblings). One payload stamp, one worker scope, timeline UI on execution detail, wait analytics on class pages. Implements the second layer of Deck’s observability story without opt-in job traits.
