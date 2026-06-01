# Dispatch groups — design spec

Automatic grouping of queue dispatches by origin (HTTP request, job lineage, or manual scope). Replaces the need for explicit “campaign” IDs in most apps.

**Status:** Draft spec (not implemented)  
**Target:** Deck 1.2+  
**Related:** [Job lifecycle](job-lifecycle.md) · [Observability v2 + Cloud sync](observability-v2-cloud-sync.md)

---

## Problem

Ops incidents often fan out across **multiple job classes** from a single user action:

- Checkout dispatches payment, inventory, email, and webhook jobs.
- An API call triggers several background tasks.
- A parent job dispatches follow-up work from the worker.

Deck today supports **class-level** block/cancel and **UUID-level** cancel, but not “stop everything that came from this request/tree” without naming each class.

Competitors solve this with manual campaign IDs on every dispatch. Deck should infer groups automatically where possible.

---

## Goals

1. **Zero config for web requests** — jobs dispatched during one HTTP request share a group.
2. **Lineage inheritance** — jobs dispatched while handling a grouped job inherit the same group by default.
3. **Manual scope** — Artisan, schedules, and replays can opt into a named group when needed.
4. **Persist on executions** — filter Activity, execution detail, and Cloud ingest by group.
5. **Operational actions (v2)** — cancel pending jobs in a group; view group summary.

## Non-goals (v1)

- Replacing Laravel `Bus::batch()` (link/display batch ID instead).
- Pausing workers or queues by group.
- Cross-installation groups (still scoped by `project` + `environment`).
- Strong guarantees for sync driver jobs in production (best-effort recording only).

---

## Terminology

| Term | Meaning |
|------|---------|
| **Dispatch group** | UUID (or app-provided string) tying related dispatches together. |
| **Group source** | How the ID was assigned: `request`, `lineage`, `manual`, `batch`. |
| **Group root** | First dispatch in the tree (often the HTTP request). |
| **Sibling** | Executions sharing the same `dispatch_group_id`. |

UI label: **“Dispatch group”** (not “campaign”).

---

## Architecture overview

```
┌─────────────────────────────────────────────────────────────────┐
│  HTTP request                                                    │
│  Middleware: ensure request dispatch group (once per request)   │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  DeckDispatcher::dispatchToQueue                               │
│  • read DispatchGroup::current()                                 │
│  • stamp payload via Queue::createPayloadUsing                   │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Redis / DB queue payload                                        │
│  deck_dispatch_group: { id, source, request_id? }                │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Worker: DeckCallQueuedHandler                                   │
│  • DispatchGroup::scopeFromPayload($job) during handle()         │
│  • child dispatches inherit same group (lineage)                 │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  RecordJobExecution → deck_job_executions.dispatch_group_*       │
└─────────────────────────────────────────────────────────────────┘
```

---

## Group ID resolution (priority order)

When stamping a dispatch, `DispatchGroup::resolveForDispatch()` returns the first applicable:

1. **Manual scope** — active `DispatchGroup::using($id)` stack frame (innermost wins).
2. **Lineage scope** — active worker scope set from parent job payload (`source: lineage`).
3. **Request scope** — HTTP request attribute (see below).
4. **Artisan scope (optional)** — when `deck.dispatch_groups.artisan_enabled`, one group per console command invocation.
5. **`null`** — no group (disabled globally, or no context).

### Request scope

Register middleware `Deck\Deck\Http\Middleware\AssignDispatchGroup` (auto-pushed to `web` when enabled, or documented for manual registration).

On first access per request:

```php
$id = $request->headers->get('X-Request-Id')
    ?? $request->attributes->get('request_id')
    ?? (string) Str::uuid();

$request->attributes->set('deck.dispatch_group_id', $id);
$request->attributes->set('deck.dispatch_group_source', 'request');
```

Prefer existing request IDs so Deck aligns with logs and APM.

### Lineage scope

In `DeckCallQueuedHandler`, before running the job pipeline:

```php
DispatchGroup::scopeFromJob($job, function () {
    // existing middleware + handle
});
```

Implementation uses a static stack (or `Illuminate\Support\Context` if Laravel 11+ context is available in the app's minimum version):

- Push `{ id, source: lineage }` from payload `deck_dispatch_group`.
- Pop in `finally` after job completes (including failures).

Child dispatches inside `handle()` see the scoped group via `DispatchGroup::current()`.

### Manual scope

```php
use Deck\Deck\Dispatch\DispatchGroup;

DispatchGroup::using('inventory-sync-2025-05-25', function () {
    ImportChunkJob::dispatch($chunk);
});

// Facade (optional sugar on Deck class):
Deck::usingDispatchGroup('nightly-rebuild', fn () => ...);
```

Manual IDs must match `^[a-zA-Z0-9._:-]{1,128}$` or be normalized to UUID.

### Opt-out

Jobs that must **not** inherit a group (e.g. housekeeping dispatched from a grouped job):

```php
DispatchGroup::withoutGroup(fn () => CleanupJob::dispatch());
```

Or trait method on job: `public function deckDispatchGroup(): ?string { return null; }` to force ungrouped for that dispatch only.

---

## Payload stamping

Use Laravel’s global payload hook (works for all job classes without traits):

```php
// DeckServiceProvider::packageBooted()
Queue::createPayloadUsing(function (string $connection, string $queue, array $payload) {
    $group = DispatchGroup::resolveForDispatch();

    if ($group === null) {
        return $payload;
    }

    $payload['deck_dispatch_group'] = [
        'id' => $group->id,
        'source' => $group->source->value,
        'request_id' => $group->requestId, // nullable, for display only
    ];

    return $payload;
});
```

`DeckDispatcher::dispatchToQueue` does **not** need to mutate the command object.

Blocked dispatches (`InterceptBlockedDispatch`) must read the same `resolveForDispatch()` when recording blocked executions so blocked rows appear in the group.

---

## Laravel batches

If `$payload['batchId']` is present (Laravel job batch):

- Store `batch_id` on execution (existing or new nullable column).
- UI links to batch progress when `batchId` is set.
- **Do not** create a separate Deck group for batch members unless `deck_dispatch_group` is also stamped (request/manual). Display batch as primary grouping when both exist.

Optional: set `dispatch_group_source = batch` and `dispatch_group_id = batchId` when batch exists and no request group — config flag, default off in v1.

---

## Data model

### Migration: `add_dispatch_group_to_deck_job_executions`

| Column | Type | Notes |
|--------|------|-------|
| `dispatch_group_id` | `string(128)` nullable | Indexed |
| `dispatch_group_source` | `string(16)` nullable | `request`, `lineage`, `manual`, `batch` |
| `batch_id` | `string(36)` nullable | Laravel batch UUID when present |

Indexes:

```php
$table->index(['project', 'environment', 'dispatch_group_id', 'started_at'], 'deck_exec_group_idx');
$table->index('batch_id');
```

Nullable for backward compatibility and jobs dispatched before upgrade.

### `QueuedJobMetadata`

Extend with:

```php
public readonly ?string $dispatchGroupId;
public readonly ?DispatchGroupSource $dispatchGroupSource;
public readonly ?string $batchId;
```

Parsed in `fromQueueJob()` from payload keys `deck_dispatch_group` and `batchId`.

### `JobExecutionRecord`

Pass through group fields from metadata; recorder persists to DB.

---

## Configuration

```php
// config/deck.php
'dispatch_groups' => [
    'enabled' => (bool) env('DECK_DISPATCH_GROUPS_ENABLED', true),

    // Auto-register AssignDispatchGroup on web middleware stack
    'request_middleware' => (bool) env('DECK_DISPATCH_GROUPS_REQUEST_MIDDLEWARE', true),

    // Inherit group when dispatching from inside a running job
    'lineage' => (bool) env('DECK_DISPATCH_GROUPS_LINEAGE', true),

    // One group per artisan command::handle() invocation
    'artisan' => (bool) env('DECK_DISPATCH_GROUPS_ARTISAN', false),

    // Header / attribute precedence for request ID reuse
    'request_id_header' => env('DECK_DISPATCH_GROUPS_REQUEST_ID_HEADER', 'X-Request-Id'),
    'request_id_attribute' => env('DECK_DISPATCH_GROUPS_REQUEST_ID_ATTRIBUTE', 'request_id'),
],
```

When `enabled => false`, no stamping, no middleware, no UI surfaces.

---

## Public API

### Core

```php
namespace Deck\Deck\Dispatch;

final class DispatchGroup
{
    public static function current(): ?DispatchGroupState;

    /** @template T */
    public static function using(string $id, callable $callback): mixed;

    /** @template T */
    public static function withoutGroup(callable $callback): mixed;

    /** Internal: resolve at payload creation time */
    public static function resolveForDispatch(): ?DispatchGroupState;

    /** Internal: worker scope from job payload */
    public static function scopeFromJob(Job $job, callable $callback): mixed;
}
```

### Facade (`Deck`)

```php
Deck::usingDispatchGroup(string $id, callable $callback): mixed;
Deck::cancelDispatchGroup(string $groupId, bool $force = false): DispatchGroupCancelResult; // v2
```

### Enums

```php
enum DispatchGroupSource: string
{
    case Request = 'request';
    case Lineage = 'lineage';
    case Manual = 'manual';
    case Batch = 'batch';
}
```

---

## UI (phased)

### v1 — read-only

**Execution detail**

- Row: **Dispatch group** — short ID + source badge (`Request`, `From parent job`, `Manual`).
- Link: “View 4 related jobs →” → Activity filtered by `dispatch_group_id`.

**Activity**

- URL param: `?group={id}`.
- Filter chip when active; show count in header.

**Global search**

- If query matches UUID/group pattern, include “Dispatch group” result linking to filtered Activity.

**Job class show**

- Optional sidebar stat: “Largest dispatch group (24h)” — defer if costly.

### v2 — actions

**Group panel** (Activity or dedicated `/deck/groups/{id}`)

| Stat | Description |
|------|-------------|
| Total | Executions in group |
| Running / pending | From DB + Redis queue scan |
| Failed | Count + link |

Actions (confirm modal):

- **Cancel pending in group** — set cancel flags + Redis payload removal (best effort), same semantics as UUID cancel.
- **Cancel running in group** — cooperative only (`Cancellable` middleware jobs).

Do **not** block job classes for a whole group in v2 (too blunt); revisit if requested.

---

## Cancel group (v2) algorithm

```php
// Pseudocode
function cancelDispatchGroup(string $groupId, bool $force): DispatchGroupCancelResult
{
    $executions = JobExecution::forInstallation()
        ->where('dispatch_group_id', $groupId)
        ->whereIn('status', [Running, /* queued rows if we add them */])
        ->get();

    foreach ($executions as $execution) {
        PendingJobCancellation::cancel($execution->uuid, $execution->connection, $execution->queue, $force);
    }

    // Also scan Redis pending payloads where payload.deck_dispatch_group.id = $groupId
    // (connection/queue list from config or discovered queues)

    return new DispatchGroupCancelResult(cancelled: ..., pendingRemoved: ...);
}
```

Requires efficient Redis scan or maintaining a group → UUID index in cache at dispatch time (optional optimization if scan is too slow).

---

## Deck Cloud

See **[observability-v2-cloud-sync.md](observability-v2-cloud-sync.md)** for the full cross-repo ingest contract. Group fields on each event:

```json
{
  "dispatch_group_id": "…",
  "dispatch_group_source": "request",
  "batch_id": null
}
```

Cloud UI correlates groups by `(tenant, project, environment, dispatch_group_id)`. Deploy Cloud ingest before agent 1.2.

---

## Edge cases

| Scenario | Behavior |
|----------|----------|
| Livewire/sub-request | Same HTTP request → same group (correct). |
| Octane / long-lived worker | Request middleware must reset group per request (standard Laravel request lifecycle). |
| `dispatchSync()` | Stamp if context exists; record via existing sync path. |
| Delayed / `dispatch()->delay()` | Group stamped at dispatch time; still grouped when run later. |
| Job retries | Same job UUID, same group on all attempts (read from payload). |
| `queue:retry` / Horizon retry | New attempt keeps payload → same group. |
| Nested manual + request | Manual `using()` overrides request scope for its closure. |
| 10k jobs one request | Valid group; cancel-group must batch Redis ops; UI warns on large groups. |
| Missing payload key (old jobs) | `dispatch_group_id` null; UI hides group section. |

---

## Security & privacy

- Group IDs are operational correlators, not PII.
- Do not put user email/order ID in group ID unless manually chosen (document risk).
- Authorization for group cancel: same as Deck dashboard (`viewHorizon` / `deck.auth`).

---

## Implementation phases

### Phase 1 — Foundation (shippable behind flag)

- [ ] `DispatchGroup` service + config
- [ ] `AssignDispatchGroup` middleware
- [ ] `Queue::createPayloadUsing` registration
- [ ] `DeckCallQueuedHandler` lineage scope
- [ ] Migration + metadata + recorder
- [ ] Tests: request grouping, lineage, manual, opt-out, disabled config

### Phase 2 — UI read-only

- [ ] Activity filter by group
- [ ] Execution detail group panel + link
- [ ] Global search hook

### Phase 3 — Ops

- [ ] `Deck::cancelDispatchGroup()`
- [ ] Group summary page or Activity header
- [ ] Cloud ingest fields

### Phase 4 — Polish

- [ ] Artisan grouping (optional config)
- [ ] Batch ID column + Horizon/deck links
- [ ] Redis group index if cancel scan is slow

---

## Test plan (Pest)

**Unit**

- `DispatchGroup::using` stack nesting and restoration
- `resolveForDispatch` priority order
- Payload hook adds/removes `deck_dispatch_group`
- `QueuedJobMetadata::fromQueueJob` parses group

**Feature**

- HTTP test: dispatch 3 jobs in one request → 3 executions, same `dispatch_group_id`, source `request`
- Job dispatches child in worker → child has same group, source `lineage`
- `withoutGroup` → child has null group
- Blocked dispatch inherits request group
- Config disabled → no columns populated
- Activity `?group=` filter returns siblings only

**Feature (v2)**

- Cancel group removes pending Redis payloads (Redis driver test)
- Running job with `Cancellable` stops on group cancel

---

## Open questions

1. **Store queued-but-not-started rows?** Queue Monitor tracks at queue time; Deck currently records at `JobProcessing`. Group cancel for “still in Redis” may need queue-time index or Redis scan only — decide in Phase 3.
2. **Artisan default on?** Default `false` to avoid surprise grouping of scheduled tasks.
3. **Group prune** — groups are not first-class rows; pruning follows execution retention. No separate TTL.

---

## Summary

Dispatch groups give Deck campaign-like ops **without manual tagging**: infer from HTTP request and job lineage, override for CLI, link Laravel batches separately. Stamp at payload creation, persist on executions, expose in Activity, cancel in v2.
