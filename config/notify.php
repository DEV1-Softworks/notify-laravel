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
    */
    'clients' => [
        'fcm' => [
            'driver' => 'fcm_v1',
            'project_id' => env('NOTIFY_FCM_PROJECT_ID'),
            'service_account_json' => storage_path(env('NOTIFY_FCM_SA_PATH', 'app/firebase/service-account.json')), // Path to JSON file or JSON string
            'scopes' => [
                'https://www.googleapis.com/auth/firebase.messaging',
            ],
            'timeout' => 10,
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
