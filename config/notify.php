<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Notify Client
    | -------------------------------------------------------------------------
    | This option controls the default notify client that will be used
    | by the framework.
    */
    'default' => env('NOTIFY_DEFAULT', 'fcm'),

    /*
    |--------------------------------------------------------------------------
    | Clients Registry
    | -------------------------------------------------------------------------
    | This option allows you to define the different Notify clients that can
    | be used by the framework. Adapter builds notify-core ClientRegistry with
    | these settings.
    |
    | You can add multiple clients with different configurations. In platform_defaults
    | you can set default options for all clients, which can be overridden
    | in each client configuration.
    */
    'clients' => [
        'fcm' => [
            'driver' => 'fcm_v1',
            'project_id' => env('NOTIFY_FCM_PROJECT_ID'),
            'service_account_json' => storage_path(env('NOTIFY_FCM_SA_PATH', 'app/firebase/service-account.json')), // Path to JSON file or JSON string
            'scopes' => [
                'https://www.googleapis.com/auth/firebase.messaging',
            ],

            // Seconds subtracted from OAuth token expiry to consider it expired.
            'cache_leeway' => env('NOTIFY_FCM_CACHE_LEEWAY', 30),

            // Optional PSR-16 cache for OAuth tokens (shares tokens across processes/workers).
            // Set to a Laravel cache store name (e.g. 'redis', 'file') to enable; null disables caching.
            'cache_store' => env('NOTIFY_FCM_CACHE_STORE'),
            'cache_key'   => env('NOTIFY_FCM_CACHE_KEY'),

            // Retry behavior on transient 5xx / 429 / transport errors.
            'max_retries'         => env('NOTIFY_FCM_MAX_RETRIES', 2),
            'retry_base_delay_ms' => env('NOTIFY_FCM_RETRY_BASE_DELAY_MS', 200),

            'platform_defaults' => [
                'android' => [],
                'apns' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    | -------------------------------------------------------------------------
    | This option allows you to configure the logging settings for notify
    | operations. You can enable or disable logging and specify the log
    | channel to be used.
    */
    'logging' => [
        'enabled' => env('NOTIFY_LOG', true),
        'channel' => env('NOTIFY_LOG_CHANNEL', null),
    ],
];
