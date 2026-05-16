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
    ],

    'retention_days' => (int) env('DECK_RETENTION_DAYS', 90),

    'cancel_ttl_seconds' => (int) env('DECK_CANCEL_TTL_SECONDS', 86_400),

    'cancel_cache_store' => env('DECK_CANCEL_CACHE_STORE'),

    'long_running_threshold_seconds' => (int) env('DECK_LONG_RUNNING_THRESHOLD_SECONDS', 300),

    'store_context' => (bool) env('DECK_STORE_CONTEXT', false),

    'tables' => [
        'job_class_stats' => 'deck_job_class_stats',
        'job_executions' => 'deck_job_executions',
    ],

    'alerts' => [
        'enabled' => (bool) env('DECK_ALERTS_ENABLED', false),
        'notification' => null,
        'stale_jobs' => [],
    ],

];
