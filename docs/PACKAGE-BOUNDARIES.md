# Package boundaries & future split

Deck is built and shipped as a **single Composer package** today, but its
internals are organised around three logical slices that are intended to become
separate packages once a deployment needs them:

| Future package | Responsibility | Has DB? | Has HTTP/cloud? |
| --- | --- | --- | --- |
| `deck/recorder` | Producer. Hooks the queue, records lifecycle transitions, fires `JobExecutionRecorded`, owns the cache-based control features (cancellation/blocking/progress reader side). | No | No |
| `deck` | Database sink + dashboard UI. Subscribes to `JobExecutionRecorded` and persists it; renders Livewire/Blade. | Yes | No |
| `deck/cloud` | Cloud sink + agent. Subscribes to `JobExecutionRecorded` and ships it to Deck Cloud; reports workers; polls remote commands. | No | Yes |

Deployment modes fall out of **composition**, not configuration:

- `recorder` + `deck` — local only (DB + UI).
- `recorder` + `cloud` — cloud-only agent (no DB).
- `recorder` + `deck` + `cloud` — both sinks fire (today's "mirror" mode).

The seam between them is the `JobExecutionRecorded` event (carrying the
`JobExecutionRecord` DTO). That DTO is the **cross-package contract** — version
it deliberately once the packages are split.

## Why it is not split yet

The architecture (event seam) and the decoupling (DB-free producer) are done
in-package. The physical Composer split is deferred until a concrete agent
deployment can't tolerate `deck`'s UI dependencies, because:

- Splitting public packages and later moving the boundary is painful; merging
  them back is trivial. Validate the boundary in practice first.
- The eventual packages use different namespace prefixes (`Deck\Recorder\`,
  `Deck\Cloud\`, and `Deck\Deck\` for `deck`). Renaming into intermediate
  `Deck\Deck\Recorder\…` namespaces now would mean re-prefixing **again** at
  split time — strictly more churn, not less. So source files keep their
  current namespaces until the split, when recorder/cloud files re-prefix once.

## Slice → file mapping

### `deck/recorder` (producer — verified DB-free and cloud-free)

```
Listeners/RecordJobExecution
Recorders/DispatchingJobExecutionRecorder
Contracts/JobExecutionRecorder
Events/*
Recording/*                      (JobExecutionTiming, JobExecutionTimingState, JobProgress, QueuedJobMetadata, QueuedJobResolver)
Blocking/*                       (JobClassBlock, InterceptBlocked*, BlockedJobExecutionRecorder, JobClassIdentifierRegistry)
Cancellation/JobCancellation     (reader side only)
Bus/* · Queue/* · Dispatch/*
Middleware/* · Http/Middleware/AssignDispatchGroup
Enums/* · Exceptions/*
Data/JobExecutionRecord · Data/ObservabilitySnapshot · Data/JobProgressState · Data/JobClassBlockAudit
Core/*                           (DeckResilience, DeckInstallation, DeferDeckSideEffects, Concerns/RunsSilently)
```

### `deck` (DB sink + UI)

```
Recorders/DatabaseJobExecutionRecorder
Models/*
Livewire/* · Presentation/* · views · routes · migrations
Cancellation/MarkExecutionCancelled · Cancellation/JobExecutionRetry   (management side)
Commands/PruneCommand · Commands/CheckAlertsCommand · Commands/InstallCommand · Commands/DoctorCommand
```

### `deck/cloud` (cloud sink + agent)

```
Recorders/HttpJobExecutionRecorder
Cloud/*
Concerns/RegistersCloudAgent
Listeners/FlushDeckCloudEvents · Listeners/SyncCloudAgent
Commands/CloudBackfillCommand · Commands/PollCommandsCommand · Commands/ReportWorkersCommand · Commands/RunScheduledCommand
```

`DeckServiceProvider` is the wiring root and references all three slices; at
split time it divides into one provider per package.

## Known cross-couplings

The recorder slice is fully clean (no inbound dependency on `deck` or `cloud`).
The only cross-slice edges are between `deck` and `cloud`, and both are
optional/bridge features — neither blocks extracting the recorder:

1. **Cloud backfill reads the database.**
   `Cloud/Events/CloudExecutionBackfillPayload`,
   `Cloud/Events/CloudObservabilityIngestFields`, and
   `Commands/CloudBackfillCommand` import `Models\JobExecution`. Backfill reads
   local executions and pushes them to cloud, so it inherently needs both the DB
   and cloud transport. **At split it belongs on the `deck` side** (which has the
   DB) and depends on `deck/cloud` for transport — it cannot live in a pure
   no-DB `deck/cloud`.

2. **The dashboard renders cloud connection status.**
   `Livewire/Dashboard` imports `Cloud\…` to show connection state. This makes
   `deck`'s UI optionally depend on `deck/cloud`; gate it behind an
   "is cloud installed/enabled" check so `deck` works standalone.

## Split procedure (when the time comes)

1. `git mv` each slice's directories into its new package.
2. Re-prefix namespaces: recorder files `Deck\Deck\X → Deck\Recorder\X`; cloud
   files `Deck\Deck\Cloud\X → Deck\Cloud\X`; `deck` files keep `Deck\Deck\`.
3. Split `DeckServiceProvider` into one provider per package. Each sink package
   registers its own `JobExecutionRecorded` listener (DB before cloud preserved
   only when both are installed).
4. Declare dependencies: `deck` and `deck/cloud` both require `deck/recorder`.
   Move backfill to `deck` with a `deck/cloud` dependency; guard the dashboard's
   cloud-status panel behind an optional check.
5. Freeze `JobExecutionRecord` as the published contract between packages.
