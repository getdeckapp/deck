# Horizon & Deck

Deck does **not** replace Horizon. Keep `php artisan horizon` in production.

| Horizon gives you | Deck adds |
|-------------------|-----------|
| Worker supervision and auto-balancing | Per job-class **last run** and status |
| Recent jobs in Redis (short retention) | **Durable execution log** in your database |
| Failed job retry UI | **Search and filter** by class, queue, connection, tag |
| Throughput and wait-time metrics | **Cooperative cancel** for opt-in jobs |
| Tags on failed jobs (limited elsewhere) | **Tags** on every recorded run |
| — | **Block job classes** at dispatch during incidents |
| — | **Stale-job** and **unprocessed-queue** alerts |

```text
┌─────────────────────────────────────────────────────────┐
│  Your Laravel app                                        │
├──────────────────────────┬──────────────────────────────┤
│  Horizon (runtime)       │  Deck (control plane)        │
│  • horizon artisan       │  • Queue event listeners     │
│  • Workers & balancing   │  • DB execution log          │
│  • /horizon dashboard    │  • /deck dashboard           │
│  • Recent/failed in Redis│  • Last run, search, cancel  │
└──────────────────────────┴──────────────────────────────┘
                          Redis queues
```

| Task | Use |
|------|-----|
| Worker health, scaling, supervisors, throughput | **Horizon** |
| Job-class history, search, cancel, block, alerts | **Deck** |

Common pain points: jobs vanishing from Horizon Recent before delayed runs execute; no answer to “when did `ProcessInvoice` last succeed?”; no safe way to stop or block a job class from a dashboard.
