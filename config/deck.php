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

    'cloud' => [
        'enabled' => env('DECK_CLOUD_ENABLED', false),
        'url' => env('DECK_CLOUD_URL'),
        'api_key' => env('DECK_API_KEY'),
        'timeout_seconds' => (int) env('DECK_CLOUD_TIMEOUT', 5),
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
    | Blocked classes are held on the queue: workers release the job with
    | block_release_seconds delay instead of running handle(). New dispatches
    | are affected the same way. Uses the same cache store as cancellation
    | when block_cache_store is null.
    |
    */
    'block_release_seconds' => (int) env('DECK_BLOCK_RELEASE_SECONDS', 60),

    'block_cache_store' => env('DECK_BLOCK_CACHE_STORE'),

    'block_manual_ttl_seconds' => (int) env('DECK_BLOCK_MANUAL_TTL_SECONDS', 31_536_000),

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

];
