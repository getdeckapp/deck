<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation identity (Deck Cloud–ready)
    |--------------------------------------------------------------------------
    |
    | Identifies this app in a future multi-tenant dashboard. Use stable
    | values per deployable (e.g. billing-api, worker) and per environment.
    |
    */
    'project' => env('DECK_PROJECT', env('APP_NAME', 'laravel')),

    'environment' => env('DECK_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Recorder
    |--------------------------------------------------------------------------
    |
    | database — local deck_* tables (default)
    | Future Deck Cloud: http / composite recorders via DECK_RECORDER
    |
    */
    'recorder' => env('DECK_RECORDER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | When set, Deck stores deck_* tables on this Laravel connection instead of
    | the application default. Define the connection in config/database.php,
    | then run migrations with: php artisan migrate --database=your_connection
    |
    */
    'database_connection' => env('DECK_DB_CONNECTION'),

    'cloud' => [
        'enabled' => env('DECK_CLOUD_ENABLED', false),
        'url' => env('DECK_CLOUD_URL'),
        'api_key' => env('DECK_API_KEY'),
        'timeout_seconds' => (int) env('DECK_CLOUD_TIMEOUT', 5),
        'promo' => env('DECK_CLOUD_PROMO', true),
    ],

    'route_prefix' => 'deck',

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | When null, Deck uses Horizon's authorization callback when Horizon is
    | installed. Otherwise, provide a callable that receives the request.
    |
    */
    'auth' => null,

    'horizon' => [
        'prompt_on_visit' => env('DECK_HORIZON_PROMPT', true),
        'remember_choice' => env('DECK_HORIZON_REMEMBER_CHOICE', true),
        'banner' => env('DECK_HORIZON_BANNER', true),
    ],

    'retention_days' => (int) env('DECK_RETENTION_DAYS', 90),

    'cancel_ttl_seconds' => (int) env('DECK_CANCEL_TTL_SECONDS', 86_400),

    'cancel_cache_store' => env('DECK_CANCEL_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Job class blocker
    |--------------------------------------------------------------------------
    |
    | Blocked classes are intercepted at dispatch (never pushed to the queue)
    | and recorded in Deck with a "blocked" status. Jobs already on the queue
    | are removed when a worker picks them up. Uses the same cache store as cancellation
    | when block_cache_store is null.
    |
    */
    'block_release_seconds' => (int) env('DECK_BLOCK_RELEASE_SECONDS', 60),

    'block_cache_store' => env('DECK_BLOCK_CACHE_STORE'),

    'block_manual_ttl_seconds' => (int) env('DECK_BLOCK_MANUAL_TTL_SECONDS', 31_536_000),

    'block_reason_max_length' => (int) env('DECK_BLOCK_REASON_MAX_LENGTH', 500),

    /*
    |--------------------------------------------------------------------------
    | Defer Deck side effects during web requests
    |--------------------------------------------------------------------------
    |
    | When true, recording blocked dispatches and cancelling running jobs during
    | a block action run after the HTTP response is sent (Laravel defer()).
    | Queue workers and tests always run these immediately.
    |
    */
    'defer_side_effects' => (bool) env('DECK_DEFER_SIDE_EFFECTS', true),

    /*
    |--------------------------------------------------------------------------
    | Livewire polling intervals (seconds)
    |--------------------------------------------------------------------------
    |
    | How often Deck refreshes live views. The dashboard polls faster while
    | jobs are running. Execution detail/list views poll while jobs run or
    | cancellations are pending.
    |
    */
    'poll' => [
        'dashboard_seconds' => (int) env('DECK_POLL_DASHBOARD_SECONDS', 4),
        'dashboard_running_seconds' => (int) env('DECK_POLL_DASHBOARD_RUNNING_SECONDS', 2),
        'workers_seconds' => (int) env('DECK_POLL_WORKERS_SECONDS', 4),
        'activity_seconds' => (int) env('DECK_POLL_ACTIVITY_SECONDS', 3),
        'executions_seconds' => (int) env('DECK_POLL_EXECUTIONS_SECONDS', 2),
    ],

    'long_running_threshold_seconds' => (int) env('DECK_LONG_RUNNING_THRESHOLD_SECONDS', 300),

    'charts' => [
        'hours' => (int) env('DECK_CHART_HOURS', 24),
    ],

    'store_context' => (bool) env('DECK_STORE_CONTEXT', false),

    'exception_trace_bytes' => (int) env('DECK_EXCEPTION_TRACE_BYTES', 65_536),

    'tables' => [
        'job_class_stats' => 'deck_job_class_stats',
        'job_executions' => 'deck_job_executions',
    ],

    'alerts' => [
        'enabled' => (bool) env('DECK_ALERTS_ENABLED', false),
        'notification' => env('DECK_ALERTS_NOTIFICATION'),
        'notifiable' => env('DECK_ALERTS_NOTIFIABLE'),
        'stale_jobs' => [
            // 'App\\Jobs\\SyncInventory' => ['max_age_hours' => 24],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unprocessed queue detection (Horizon)
    |--------------------------------------------------------------------------
    |
    | Detect queues with pending jobs but no Horizon worker processes assigned.
    | Requires Horizon; without it, Deck does not report unprocessed queues.
    |
    */
    'unprocessed_queues' => [
        'enabled' => (bool) env('DECK_UNPROCESSED_QUEUES_ENABLED', true),
        'min_pending' => (int) env('DECK_UNPROCESSED_QUEUES_MIN_PENDING', 1),
        'include_alerts' => (bool) env('DECK_UNPROCESSED_QUEUES_ALERTS', true),
        'additional_queues' => [
            // 'redis:notifications',
        ],
    ],

];
