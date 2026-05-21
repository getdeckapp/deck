# Changelog

All notable changes to `deck/deck` are documented in this file.

## 1.0.0 - 2026-05-21

First public release.

### Added

- Livewire dashboard at `/deck` — overview, job classes, activity log, and workers view
- Durable job execution recording scoped by `project` and `environment`
- Cooperative job cancellation (`Cancellable` middleware) and job-class dispatch blocking
- Horizon integration — shared authorization, visit prompt, workload snapshots, failed-job links, unprocessed-queue detection
- Precompiled Tailwind assets via `deck:install` (no Vite setup required in host apps)
- Artisan commands: `deck:install`, `deck:prune`, `deck:check-alerts`, `deck:report-workers`
- Optional **Deck Cloud** agent — worker ingest, remote commands, execution event sync (`DECK_API_KEY`)
- Stale-job and failure-rate alerts via Laravel notifications
- Job progress reporting, queue admin (clear pending), and runtime analytics (avg, p50, p95)

### Requirements

- PHP 8.3+, Laravel 11–13, Livewire 3.5+ or 4.x
- Database for execution history; Redis recommended for queues and cancel/block flags
- Laravel Horizon 5.x recommended (optional; package works without Horizon for basic recording)
