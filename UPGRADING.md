# Upgrading

## Upgrading to 1.1.15 or later from an earlier version

Deck now **discovers and runs its own migrations** from the package — you no longer
publish migration stubs into your app. The package migrations are idempotent (they
guard every `create`/column/index with `hasTable` / `hasColumn` / `hasIndex`), so
they run safely against tables you already have.

**If you published Deck migrations on a version before 1.1.15**, those copies are
still sitting in your app's `database/migrations` directory. They have no existence
guards and will re-run after the package's own migrations already created the tables,
failing with **`table already exists`**.

To upgrade cleanly, delete the published copies before migrating:

```
database/migrations/*_create_deck_tables.php
database/migrations/*_add_project_and_environment_to_deck_tables.php
database/migrations/*_add_exception_trace_to_deck_job_executions.php
database/migrations/*_add_created_at_index_to_deck_job_executions.php
database/migrations/*_add_observability_v2_to_deck_job_executions.php
```

Your existing Deck tables and data are left untouched — the package migrations detect
them and are simply recorded as run. `php artisan deck:doctor` (and `deck:install`)
will list any stray published copies it finds.

## `deck:report-workers` no longer fails a scheduled run when there is nothing to report

`deck:report-workers` is scheduled every minute when Deck Cloud is enabled. Previously
it exited non-zero when there were no Horizon supervisors and no queue workload (for
example on a `sync` queue, or before Horizon is up), which made Laravel's scheduler
raise `ScheduledTaskFailed` and page error reporters such as Flare on every tick.

It now exits `0` for these benign states (and for throttled sends), emitting a warning
instead. A non-zero exit is now reserved for a genuine rejection by Deck Cloud
(401/422/5xx). No action is required on your side.
