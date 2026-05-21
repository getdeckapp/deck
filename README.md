<p align="center">
  <img src="resources/images/deck-banner.png" alt="Deck — queue control plane for Laravel" width="640">
</p>

# Deck

**Job-class observability and safe cancellation for Laravel apps running [Horizon](https://laravel.com/docs/horizon).**

> Horizon flies the workers. Deck runs the operation.

Deck is a **durable control plane** on top of Horizon: per job-class history, searchable executions, cooperative cancel, dispatch blocking, and optional **[Deck Cloud](https://deckapp.cloud)** when you run many services. It does not replace `php artisan horizon`.

---

## Quick start

```bash
composer require deck/deck
php artisan deck:install
php artisan migrate
```

Open `/deck`. Reuse Horizon’s authorization gate by default.

```env
DECK_PROJECT=billing-api
DECK_ENVIRONMENT=production
```

Optional Cloud agent (three variables):

```env
DECK_API_KEY=your-agent-token
```

---

## Documentation

Full guides live on **[deckapp.cloud/docs](https://deckapp.cloud/docs)** (mirrored in this repo under [`docs/`](docs/)):

| Guide | |
|-------|---|
| [Getting started](https://deckapp.cloud/docs/getting-started) | Install, migrate, Horizon auth, assets |
| [Horizon & Deck](https://deckapp.cloud/docs/horizon) | What each tool is for |
| [Usage](https://deckapp.cloud/docs/usage) | Dashboard, cancel, block, retry, alerts |
| [Production](https://deckapp.cloud/docs/production) | Dedicated DB, retention, Redis, security |
| [Configuration](https://deckapp.cloud/docs/configuration) | Environment variables and config keys |
| [Deck Cloud](https://deckapp.cloud/docs/deck-cloud) | Multi-app control plane and agent API |

**Contributors:** [IMPLEMENTATION.md](IMPLEMENTATION.md) (architecture), [CHANGELOG.md](CHANGELOG.md).

---

## Why Deck?

| Horizon gives you | Deck adds |
|-------------------|-----------|
| Worker supervision and auto-balancing | Per job-class **last run** and status |
| Recent jobs in Redis (short retention) | **Durable execution log** in your database |
| Failed job retry UI | **Search and filter** by class, queue, connection, tag |
| Throughput and wait-time metrics | **Cooperative cancel** and **block job classes** |
| — | **Stale-job** and **unprocessed-queue** alerts |

Every row is scoped by `project` and `environment` for multi-app teams and Deck Cloud.

---

## Requirements

PHP 8.3+ · Laravel 11–13 · Database · Redis (queues + cancel/block flags) · [Horizon](https://laravel.com/docs/horizon) 5.x recommended

---

## Development

```bash
composer test
```

---

## License

MIT © [Tor Morten Jensen](https://github.com/tormjens). See [LICENSE.md](LICENSE.md).
