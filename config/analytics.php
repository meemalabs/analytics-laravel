<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate with the analytics server.
    | Must start with "ak_".
    |
    */

    'token' => env('ANALYTICS_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Site ID
    |--------------------------------------------------------------------------
    |
    | The site identifier for this application.
    |
    */

    'site_id' => env('ANALYTICS_SITE_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The environment name sent with error reports.
    | Defaults to the application environment.
    |
    */

    'environment' => env('ANALYTICS_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Kill switch to disable the analytics log driver entirely.
    |
    */

    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Minimum Log Level
    |--------------------------------------------------------------------------
    |
    | The minimum Monolog log level required to send a report.
    | Defaults to "error".
    |
    */

    'level' => env('ANALYTICS_LOG_LEVEL', 'error'),

];
