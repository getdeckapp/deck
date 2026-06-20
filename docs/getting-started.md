# Getting started

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13
- [Laravel Horizon](https://laravel.com/docs/horizon) 5.x recommended (Redis queues)
- A database for Deck’s execution log
- Redis for queues and for cancel/block flags (typically the same instance as Horizon)

Deck works without Horizon for basic recording; Horizon unlocks workload snapshots, failed-job retry, and unprocessed-queue detection.

## Install

```bash
composer require deck/deck
php artisan deck:install
php artisan migrate
```

`deck:install` publishes `config/deck.php` and precompiled CSS to `public/vendor/deck`. Deck's migrations run from the package itself, so `php artisan migrate` creates the `deck_*` tables — nothing to publish.

Open `/deck` (prefix: `deck.route_prefix`).

**Deck does not run queue workers.** Keep `php artisan horizon` or `php artisan queue:work` running — installing Deck only adds observability and controls on top of your existing queue.

### Dedicated database

For busy apps, use a separate connection — see [Production → Dedicated database](production.md#dedicated-database-recommended).

### Horizon authorization

By default Deck uses **the same authorization as Horizon** (`Horizon::auth()` / `viewHorizon` gate).

`deck:install` can add middleware so the first visit to `/horizon` offers **Deck** or **Horizon**. Set `DECK_HORIZON_PROMPT=false` to disable. Horizon’s runtime (`php artisan horizon`) is never affected.

### UI assets

The dashboard ships **precompiled Tailwind CSS** — no Vite setup in your app. Re-run `deck:install --force` after upgrading the package.

## Identity (recommended)

```env
DECK_PROJECT=billing-api
DECK_ENVIRONMENT=production
```

Stable values per deployable keep stats and history from colliding across apps and environments.

## Deck Cloud (optional)

```env
DECK_API_KEY=your-agent-token
```

See [Deck Cloud](deck-cloud.md). Overview: [deckapp.cloud](https://deckapp.cloud).

## Next steps

- [Usage](usage.md) — dashboard, cancel, block, retry
- [Production](production.md) — retention, Redis, security
- [Configuration](configuration.md) — environment variables
