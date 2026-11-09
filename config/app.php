<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name' => env('STORE_NAME', 'store'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost:8000'),

    'timezone' => 'Asia/Tehran',

    /*
    |--------------------------------------------------------------------------
    | Localisation
    |--------------------------------------------------------------------------
    |
    | The platform is Farsi only. Persian is the application locale, the
    | fallback, and the only locale exposed to visitors.
    |
    */

    // DECISION (farsi-only): there is one locale. Persian is the application,
    // fallback and only exposed locale; direction is RTL everywhere.
    'locale' => 'fa',

    'fallback_locale' => 'fa',

    'faker_locale' => 'fa_IR',

    'direction' => 'rtl',

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
