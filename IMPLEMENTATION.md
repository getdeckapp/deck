# Deck — implementation plan

This document is the source of truth for building `tormjens/deck`. It complements [README.md](README.md) with architecture, phases, and file-level tasks.

**Status:** Phase 0 in progress (recording + Cloud-ready identity)  
**Namespace:** `Deck\Deck`  
**Package path:** `packages/deck` in `package-dev` sandbox

---

## Product summary

Deck is a **Horizon companion package**: durable job-class observability and safe cancellation. It does not implement worker supervision, balancing, or Redis queue drivers.

**Tagline:** Horizon flies the workers. Deck runs the operation.

---

## Design decisions (locked)

| Topic | Decision |
|-------|----------|
| **Dashboard stack** | **Livewire 4 + Alpine.js** — Livewire for data tables, filters, polling; Alpine for modals, dropdowns, confirm dialogs, copy-to-clipboard |
| **Visual style** | **Laravel-native, super-clean** — light/dark via `prefers-color-scheme` or app convention; generous whitespace; no Horizon clone |
| **Design system** | **Required** — shared Blade components + tokens (see [Design system](#design-system)) |
| **Layout** | **Index + detail** — job-class list → class detail with execution history (see [Layout](#layout)) |
| **Auth** | **Reuse Horizon’s authorization** — same callback / gate as `Horizon::auth()` |
| **Horizon dashboard** | **Choice prompt on visit** — first `/horizon` visit asks Horizon vs Deck; optional “remember” (session) |
| **Default route** | `/deck` |
| **Payload storage** | **No serialized payloads by default** — opt-in context only (see [Payload storage](#payload-storage)) |
| **Alerts (V1)** | **Laravel Notifications** — user configures channels in their app |

---

## Design system

Deck ships a small, consistent UI kit so every screen looks intentional—not one-off Livewire views.

### Principles

1. **Quiet UI** — typography and spacing do the work; color only for status (success, failed, running, cancelled).
2. **Laravel-familiar** — same mental model as Pulse / Telescope / default Laravel Breeze-Jetstream admin: white/slate surfaces, subtle borders, `rounded-lg`, focus rings.
3. **Composable** — pages are built from `<x-deck::*>` components only; no raw Tailwind soup in feature views.
4. **Accessible** — semantic tables, `aria` on status badges, keyboard-friendly modals (Alpine).

### Precompiled Tailwind CSS

Deck ships **precompiled CSS** in `resources/dist/deck.css`, built from package views with Tailwind v4. Host apps do **not** add `@source` paths or Vite entries for Deck.

- `deck:install` publishes assets to `public/vendor/deck` (`deck-assets` tag).
- Before publish, layouts load CSS from `{route_prefix}/assets/deck.css` (served from the package `resources/dist/`).
- Rebuild after view changes: `npm run build` in the package (or `composer build-assets`).

### Blade components (`resources/views/components/deck/`)

| Component | Use |
|-----------|-----|
| `<x-deck::badge :status="...">` | Status pill (completed, failed, running, cancelled) |
| `<x-deck::alert>` | Flash / success message |

### Livewire conventions

- One full-page component per route (`JobClassIndex`, `JobClassShow`).
- `wire:poll` only on detail view when executions are `running` (avoid global polling).
- Debounced search (`wire:model.live.debounce.300ms`).
- Loading: `wire:loading` targets on filters, not full-page spinners unless navigation.

### Package assets

| Path | Purpose |
|------|---------|
| `resources/css/deck.css` | Tailwind entry (`@source` scans `resources/views`) |
| `resources/dist/deck.css` | Committed build output (shipped to Composer) |
| `public/vendor/deck/deck.css` | Published copy in consuming apps |

`Deck\Deck\Support\DeckAssets` resolves the stylesheet URL (published file, else package route).

---

## Layout

**Chosen pattern: index + detail (two routes).**

| Route | Component | Content |
|-------|-----------|---------|
| `GET /deck` | `JobClassIndex` | Sortable table: job class, last finished, last status, success/fail counts, queue (optional column) |
| `GET /deck/classes/{jobClass}` | `JobClassShow` | Header stats + paginated executions; cancel on running rows |

**Why not a single mega-table?** Job classes are the primary ops unit (“when did `SyncInventory` last run?”). Executions are drill-down. A single table mixes scales (dozens of classes vs. thousands of runs) and hurts performance.

**Why not a slide-over drawer only?** Drawers are fine for quick peek (Alpine), but bookmarkable `/deck/classes/{encodedClass}` URLs matter for sharing links in Slack/incidents.

**Index row click** → navigate to detail. Optional Alpine drawer later for “quick peek” without leaving index (V2 polish).

---

## Payload storage

**Default: do not store serialized job commands.**

| Stored (always) | Not stored (default) |
|-----------------|----------------------|
| `job_class`, `uuid`, `connection`, `queue` | Full `serialize()` payload |
| `status`, timestamps, `duration_ms`, `attempt` | Passwords, tokens, PII in job properties |
| `exception_class`, truncated `exception_message` | Large arrays / binary |
| `tags` (from Horizon / job) | |

**Opt-in context** for debugging without opening Redis:

```php
// Job implements optional interface
interface ExposesDeckContext
{
    /** @return array<string, scalar|null> */
    public function deckContext(): array;
}
```

- Config: `store_context` => `false` (default). When `true`, persist `context` JSON column only from `deckContext()` — scalars only, max length enforced.
- Never auto-reflect job public properties (too easy to leak secrets).

---

## Horizon integration

### Authorization (Deck)

`config/deck.php`:

```php
'auth' => null, // null = delegate to Horizon when installed
```

When `auth` is `null` and `laravel/horizon` is present, Deck uses the same authorization as Horizon (`Horizon::auth()` / `viewHorizon` gate).

### Choice prompt on `/horizon` (default: enabled)

Instead of disabling Horizon’s UI, Deck **intercepts the first dashboard visit** and asks where the user wants to go.

**Intent:** Deck is the recommended ops surface for job-class history and cancellation; Horizon remains available for worker throughput, supervisors, and metrics. Users choose explicitly—no hidden lockout.

```php
'horizon' => [
    'prompt_on_visit' => env('DECK_HORIZON_PROMPT', true),
    'remember_choice' => env('DECK_HORIZON_REMEMBER_CHOICE', true),
],
```

#### User flow

```text
User opens GET /horizon (dashboard shell, not /horizon/api/*)
        │
        ▼
Session has deck_horizon_preference?
        │
   yes ─┴─► "deck"  → redirect to /deck
        │    "horizon" → continue to Horizon SPA
        │
   no  ───► Full-page prompt (Blade + Alpine, design system)
              • "Go to Deck" (recommended) → set preference → /deck
              • "Continue to Horizon"       → set preference → /horizon
              • [ ] Remember my choice (if remember_choice)
```

#### Why a full page, not a modal on Horizon?

Horizon is a Vue SPA. The prompt must run **before** the SPA loads, via middleware on Horizon’s HTTP middleware stack—not injected into Vue.

#### Middleware: `PromptHorizonOrDeck`

Registered on Horizon’s route middleware (see `config/horizon.php` → `middleware`).

| Request | Behavior |
|---------|----------|
| `GET /{horizon.path}` (root only) | Prompt or redirect per preference |
| `GET /{horizon.path}/api/*` | Always pass through (no prompt on polling) |
| `GET /deck/*` | No prompt |

Session key: `deck_horizon_preference` → `deck` | `horizon`.

Optional: `POST /deck/horizon-preference` to switch later from Deck settings footer (“Open Horizon dashboard”).

#### Prompt view

`resources/views/horizon-prompt.blade.php` — uses `<x-deck::layout>` + `<x-deck::panel>`:

- Short copy: Deck is installed; Horizon is for workers/metrics, Deck for job-class history and cancel.
- Primary button: **Go to Deck**
- Secondary: **Continue to Horizon**
- Checkbox: Remember my choice (Alpine + form POST)

#### `deck:install` integration

1. Append `Deck\Deck\Http\Middleware\PromptHorizonOrDeck::class` to `config/horizon.php` `middleware` array (or print manual step if config not published).
2. Do **not** modify `Horizon::auth()`.

#### Disabling the prompt

```env
DECK_HORIZON_PROMPT=false
```

Horizon behaves as stock; Deck is only at `/deck`.

#### Clearing preference

Document `session()->forget('deck_horizon_preference')` or a `deck:clear-horizon-preference` artisan command (V1 nice-to-have).

**Workers are unaffected** — `php artisan horizon` and all runtime behavior unchanged.

---

## Notifications (V1 alerts)

Use standard **Laravel Notifications**; Deck does not ship Slack/Mail drivers.

```php
// config/deck.php
'alerts' => [
    'enabled' => env('DECK_ALERTS_ENABLED', false),
    'notification' => \App\Notifications\DeckAlertNotification::class,
    'stale_jobs' => [
        // 'App\Jobs\SyncInventory' => ['max_age_hours' => 24],
    ],
],
```

Scheduled command `deck:check-alerts` resolves stale rules → `$user->notify(new DeckAlertNotification(...))` or Notifiable route from config.

Host app defines channels (`mail`, `slack`, `database`, etc.) on their notification class.

---

## Architecture

```text
Dispatch / worker
       │
       ▼
Illuminate\Queue\Events\*
       │
       ▼
Deck\Listeners\RecordJobExecution
       │
       ├──► deck_job_executions (append)
       └──► deck_job_class_stats (upsert)
       │
       ▼
Dashboard (HTTP) + Deck::cancel() API
       │
       └──► Redis: deck:cancel:{uuid}
                 │
                 ▼
            Cancellable middleware (opt-in jobs)
```

### Boundaries

| In scope | Out of scope |
|----------|----------------|
| Queue event recording | Replacing Horizon supervisors |
| DB retention and prune | SQS / database queue drivers |
| Cooperative cancel | Force-kill OS worker processes |
| Pending cancel (best-effort, V1) | Hold / move / delay queue position (V3+) |
| Link to Horizon for retry | Forking Horizon’s Vue dashboard |

---

## Data model

### `deck_job_class_stats`

One row per **project + environment + job class** (fast “last run” lookup; Cloud-ready).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `project` | string | `DECK_PROJECT` — deployable id |
| `environment` | string | `DECK_ENVIRONMENT` |
| `job_class` | string | FQCN from `$event->job->resolveName()` |
| `last_started_at` | timestamp, nullable | |
| `last_finished_at` | timestamp, nullable | |
| `last_status` | string | `running`, `completed`, `failed` |
| `last_duration_ms` | unsigned int, nullable | |
| `last_uuid` | uuid, nullable | Link to latest execution |
| `success_count` | unsigned bigint | Increment on success |
| `failure_count` | unsigned bigint | Increment on failure |
| `created_at` / `updated_at` | timestamps | |

Unique: `(project, environment, job_class)`.

### `deck_job_executions`

Append-only execution log (pruned by `deck:prune`).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `project` | string | Same as stats |
| `environment` | string | Same as stats |
| `uuid` | uuid, indexed | Job UUID from payload |
| `job_class` | string, indexed | |
| `connection` | string | |
| `queue` | string, indexed | |
| `status` | string | `running`, `completed`, `failed`, `cancelled` |
| `attempt` | unsigned tinyint | |
| `tags` | json, nullable | From Horizon tags when available |
| `started_at` | timestamp | |
| `finished_at` | timestamp, nullable | |
| `duration_ms` | unsigned int, nullable | |
| `exception_class` | string, nullable | |
| `exception_message` | text, nullable | Truncated |
| `created_at` | timestamp | |

**Indexes:** `(job_class, started_at DESC)`, `(status, started_at)`, `(uuid)` unique optional per attempt policy.

### Cancel flag (Redis)

- Key: `deck:cancel:{uuid}`
- Value: `1`
- TTL: `config('deck.cancel_ttl_seconds')` (default 86400)

---

## Event wiring

| Event | Action |
|-------|--------|
| `JobProcessing` | Insert execution `running`; upsert stats `last_started_at`, `last_status=running` |
| `JobProcessed` | Update execution `completed`, duration; increment `success_count` |
| `JobFailed` | Update execution `failed`, store exception summary; increment `failure_count` |
| Custom / cancelled | Middleware sets `cancelled` before fail |

**Idempotency:** Upsert execution by `uuid` + `attempt` if retries create duplicate processing events.

**Async writes (optional later):** Dispatch `RecordJobExecutionJob` if write volume is high; not in MVP.

---

## Package structure (target)

```text
packages/deck/
├── config/deck.php
├── database/migrations/
├── resources/views/          # or Livewire components
├── routes/web.php
├── src/
│   ├── DeckServiceProvider.php
│   ├── Deck.php                    # Cancel, query helpers
│   ├── Facades/Deck.php
│   ├── Commands/
│   │   ├── InstallCommand.php
│   │   └── PruneCommand.php
│   ├── Enums/JobExecutionStatus.php
│   ├── Http/
│   │   └── Middleware/AuthorizeDeck.php
│   ├── Listeners/RecordJobExecution.php
│   ├── Middleware/Cancellable.php
│   ├── Models/
│   │   ├── JobClassStat.php
│   │   └── JobExecution.php
│   ├── Support/JobCancellation.php
│   └── Livewire/                 # if Livewire chosen
│       ├── JobClassIndex.php
│       └── JobClassShow.php
└── tests/
    ├── Feature/
    │   ├── RecordingTest.php
    │   ├── CancellationTest.php
    │   └── DashboardTest.php
    └── Unit/
        └── JobCancellationTest.php
```

Remove scaffold artifacts when implementing:

- `Deck::echoPhrase()` placeholder
- `create_deck_table` stub migration (replace with real migrations)
- Spatie sponsor boilerplate in README (done)

---

## Configuration (`config/deck.php`)

```php
return [
    'route_prefix' => 'deck',
    'middleware' => ['web'],
    'auth' => null, // null = delegate to Horizon authorization

    'horizon' => [
        'prompt_on_visit' => env('DECK_HORIZON_PROMPT', true),
        'remember_choice' => env('DECK_HORIZON_REMEMBER_CHOICE', true),
    ],

    'database_connection' => env('DECK_DB_CONNECTION'), // null = app default

    'retention_days' => 90,
    'cancel_ttl_seconds' => 86_400,
    'long_running_threshold_seconds' => 300,
    'store_context' => false,

    'tables' => [
        'job_class_stats' => 'deck_job_class_stats',
        'job_executions' => 'deck_job_executions',
    ],

    'alerts' => [
        'enabled' => env('DECK_ALERTS_ENABLED', false),
        'notification' => null, // FQCN of Illuminate\Notifications\Notification
        'stale_jobs' => [],
    ],
];
```

---

## Phase 0 — MVP

**Goal:** Answer “when did this job class last run?” and cancel opt-in long jobs.

### Tasks

- [ ] **M0.1** Replace stub migration with `deck_job_class_stats` + `deck_job_executions`
- [ ] **M0.2** Eloquent models + factories for tests
- [ ] **M0.3** `RecordJobExecution` listener registered in service provider
- [ ] **M0.4** `JobExecutionStatus` enum
- [ ] **M0.5** `JobCancellation` + `Cancellable` middleware + `Deck::cancel()`
- [ ] **M0.6** `deck:install` command (publish config + migrations)
- [ ] **M0.7** `deck:prune` command
- [ ] **M0.8** Authorization middleware (Horizon auth delegation)
- [ ] **M0.8b** `PromptHorizonOrDeck` middleware + prompt view + preference routes
- [ ] **M0.8c** `deck:install` — append middleware to `config/horizon.php`
- [x] **M0.9** Design system: Tailwind-only views + `<x-deck::badge>` / `<x-deck::alert>`
- [ ] **M0.10** Livewire `JobClassIndex` — sortable class list
- [ ] **M0.11** Livewire `JobClassShow` — paginated executions, Alpine confirm cancel
- [ ] **M0.12** Cancel action on running execution (sets Redis flag)
- [ ] **M0.13** Pest tests: recording, aggregates, cancel flow, auth gate, Livewire pages
- [ ] **M0.14** Document Horizon disable + `composer suggest` `laravel/horizon`

### MVP definition of done

1. Dispatch a test job in `package-dev` → row appears in stats and executions.
2. `/deck` shows class with correct last finished time.
3. Cancellable job stops between steps when cancelled.
4. `deck:prune` removes old rows; tests pass in CI.

### Estimated effort

~2–3 weeks solo (including UI choice).

---

## Phase 1 — V1 ops

| ID | Feature | Notes |
|----|---------|-------|
| V1.1 | Filter by queue + connection | Index already on executions |
| V1.2 | Search by job class name | `where job_class like` |
| V1.3 | Status filter | completed / failed / running |
| V1.4 | Tags — capture on record | Horizon `Tags` when job uses `Taggable` |
| V1.5 | Tag filter on dashboard | |
| V1.6 | Pending cancel by UUID | Best-effort Redis removal; document races |
| V1.7 | Long-running highlight | `running` + `started_at` threshold |
| V1.8 | Stale job alerts | `deck:check-alerts` + Laravel Notification class in host app |
| V1.9 | `composer require` Horizon peer dependency clarity | README + optional service provider hook |

---

## Phase 2 — V2 analytics (shipped in package)

| ID | Feature | Notes |
|----|---------|-------|
| V2.1 | Runtime rollups (avg, p50, p95) | `RuntimeRollups` on job-class detail; window = `deck.charts.hours` |
| V2.2 | `JobProgress::update($uuid, %)` | Redis/cache + progress bar on execution detail |
| V2.3 | Failure-rate alerts | `deck.alerts.failure_rate_jobs` + `deck:check-alerts` console output |
| V2.4 | Link to Horizon failed job | `DeckHorizon::failedJobUrl()` on failed execution detail |
| V2.5 | Clear single queue (admin) | Workers page; Redis only; `deck.queue_admin` config |

---

## Phase 3 — deferred

- WebSockets / Echo live table updates
- Prometheus export
- Supervisor pause/terminate UI (Horizon already has CLI)
- Non-Redis drivers
- Hold / move / delay queue operations

---

## Testing strategy

| Area | Approach |
|------|----------|
| Recording | Orchestra Testbench + `Queue::fake()` or sync driver + real listener |
| Aggregates | Assert `JobClassStat` after `JobProcessed` / `JobFailed` |
| Cancel | Feature test: start long job, set cancel flag, assert `JobCancelledException` |
| Dashboard | Livewire tests or HTTP tests with authorized user |
| Prune | Seed old rows, run `deck:prune`, assert count |

Run from package root:

```bash
composer test
vendor/bin/phpstan analyse
vendor/bin/pint
```

---

## Risk register

| Risk | Mitigation |
|------|------------|
| High job volume → DB pressure | Prune aggressively; optional async recorder later |
| Duplicate events on retry | Composite key `uuid` + `attempt` |
| Pending cancel races | UI copy: “best effort”; optional dispatch-time pending registry in V1 |
| PII in exceptions | Truncate messages; never log full payload |
| Horizon not installed | Document requirement; graceful package install without Horizon for recording-only |

---

## Sandbox integration (`package-dev`)

The host app already references the path repo:

```json
"repositories": [{ "type": "path", "url": "packages/*" }],
"require-dev": { "tormjens/deck": "dev-master" }
```

MVP validation checklist in sandbox:

1. `composer update tormjens/deck`
2. `php artisan deck:install && php artisan migrate`
3. Configure Redis + Horizon (or `queue:work` for minimal test)
4. Dispatch sample jobs from `routes/web.php` or a test route
5. Verify `/deck` and cancellation

---

## Composer / release

| Item | Value |
|------|-------|
| Package name | `tormjens/deck` |
| PHP | `^8.4` |
| Laravel | `^11.0\|^12.0\|^13.0` |
| Suggested | `laravel/horizon: ^5.0` |
| Tools | Spatie package-tools, Pest, Pint, PHPStan |

Before Packagist 1.0: remove `minimum-stability: dev` consumer friction, tag `v0.1.0` after MVP tests green.

---

## Deck Cloud (future)

**Vision:** One dashboard at work for every Laravel app and environment you run — without replacing Horizon or hosting customer Redis.

Deck (self-hosted package) remains the **agent**. Deck Cloud is the **multi-tenant control plane** that ingests the same events the local recorder sees today.

### Problem it solves

| Today | Deck Cloud |
|-------|------------|
| `/horizon` per app | Worker UI still per app (optional) |
| `/deck` per app | **One URL** — filter by project, env, queue, job class |
| Tab fatigue across 6 codebases | Unified stale-job / failure view |
| No cross-env “last success” | SLOs per job class per project |

### Architecture

```text
┌─────────────────┐     HTTPS (events)      ┌──────────────────────┐
│  App A + deck   │ ───────────────────────►│                      │
│  package        │                         │  Deck Cloud API        │
├─────────────────┤                         │  • ingest            │
│  App B + deck   │ ───────────────────────►│  • multi-tenant DB   │
│  package        │                         │  • dashboard         │
├─────────────────┤                         │  • alerts            │
│  App C + deck   │ ───────────────────────►│                      │
└─────────────────┘                         └──────────────────────┘
        │                                              ▲
        │  Horizon / queue:work still local            │
        └──────────────────────────────────────────────┘
              cancel flags stay in customer Redis
```

**Out of scope for Cloud v1:** Hosted workers, hosted Redis, replacing SQS.

### Identity (implemented in package now)

Every recorded row is tagged:

| Field | Config | Example |
|-------|--------|---------|
| `project` | `DECK_PROJECT` (default `APP_NAME`) | `billing-api` |
| `environment` | `DECK_ENVIRONMENT` (default `APP_ENV`) | `production` |

Stats are unique per `(project, environment, job_class)` so the same job class in staging and prod never collide.

**Work setup:** Use stable `DECK_PROJECT` per deployable across your team (not machine-specific names).

### Recorder abstraction (implemented now)

```text
Queue event → RecordJobExecution → JobExecutionRecorder::record(JobExecutionRecord)
                                              │
                                    ┌─────────┴─────────┐
                                    ▼                   ▼
                        DatabaseJobExecutionRecorder   (future)
                        local deck_* tables            HttpJobExecutionRecorder
                                                     → Deck Cloud ingest API
```

| Class | Role |
|-------|------|
| `JobExecutionRecord` | DTO sent to any recorder |
| `JobExecutionRecorder` | Contract |
| `DatabaseJobExecutionRecorder` | Default — local DB |
| `HttpJobExecutionRecorder` | **Future** — batch POST to Cloud |
| `CompositeJobExecutionRecorder` | **Future** — DB + Cloud |

Config today:

```php
'recorder' => env('DECK_RECORDER', 'database'),
'cloud' => [
    'enabled' => env('DECK_CLOUD_ENABLED', false),
    'url' => env('DECK_CLOUD_URL'),
    'api_key' => env('DECK_API_KEY'),
],
```

### Cloud ingest event (draft schema)

Same shape as `JobExecutionRecord` JSON (no full job payload by default):

```json
{
  "project": "billing-api",
  "environment": "production",
  "job_class": "App\\Jobs\\SyncInvoices",
  "uuid": "...",
  "connection": "redis",
  "queue": "default",
  "status": "completed",
  "attempt": 1,
  "tags": ["billing"],
  "started_at": "2026-05-16T12:00:00Z",
  "finished_at": "2026-05-16T12:00:05Z",
  "duration_ms": 5120,
  "exception_class": null,
  "exception_message": null
}
```

Agent sends over HTTPS with `Authorization: Bearer {DECK_API_KEY}`. Idempotency: `(project, environment, uuid, attempt, status)`.

### Cloud product phases

| Phase | Ships |
|-------|--------|
| **C0** | Package identity + recorder contract ✅ (this repo) |
| **C1** | Ingest API + API keys + project/env switcher UI (read-only) |
| **C2** | Alerts (stale job, failure rate), team members |
| **C3** | Remote cancel (agent polls or receives webhook → Redis flag) |
| **C4** | Retry orchestration via agent (not raw Horizon API from Cloud) |

### Commercial sketch (optional)

- Free: 1 project, 2 environments, 7-day retention  
- Team: unlimited projects/envs, 90-day retention, Slack alerts  
- Self-hosted Deck package stays MIT; Cloud is hosted SaaS  

### Competitive note

[Queuewatch](https://queuewatch.io) and similar tools validate “Laravel queue SaaS.” Deck Cloud wedge: **multi-app work dashboard**, **job-class SLOs**, **Horizon coexistence**, optional **self-hosted** without vendor lock-in for recording.

---

## Next steps

1. **Implement:** Phase 0 — cancel → design system → Livewire pages.
2. **Migrate:** Run new Deck migration for `project` / `environment` columns (`add_project_and_environment_to_deck_tables`).
3. **Work dogfood:** Set `DECK_PROJECT` per app; imagine Cloud switcher while building UI filters.
4. **Cloud:** Defer until package MVP is used daily; recorder contract is ready.
