<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Product identity
    |--------------------------------------------------------------------------
    |
    | "store" is deliberately generic: everything tenant-specific is stored per
    | account, and nothing in the codebase assumes a particular brand.
    |
    */

    'name' => env('STORE_NAME', 'store'),

    /*
    |--------------------------------------------------------------------------
    | Landlord connection
    |--------------------------------------------------------------------------
    |
    | The account tree and the hostname registry live in a single database.
    | Tenant content never does.
    |
    */

    'landlord_connection' => env('STORE_LANDLORD_CONNECTION', 'landlord'),

    /*
    |--------------------------------------------------------------------------
    | Default account
    |--------------------------------------------------------------------------
    |
    | Used when a hostname is not registered, so development servers and
    | preview URLs always render a storefront instead of a 404. In production
    | set this to the platform account id so unknown hosts get no storefront.
    |
    */

    'default_account' => env('STORE_DEFAULT_ACCOUNT'),

    /*
    |--------------------------------------------------------------------------
    | Platform account
    |--------------------------------------------------------------------------
    |
    | The root of the account tree. It holds no storefront and no database; it
    | exists to parent top-level accounts and to hold platform staff.
    |
    */

    'platform_account_id' => env('STORE_PLATFORM_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Convenience domains
    |--------------------------------------------------------------------------
    |
    | Hosts that are treated as the control plane rather than a tenant
    | storefront. Requests to these hosts get the platform account.
    |
    */

    'control_plane_hosts' => array_filter(explode(',', (string) env('STORE_CONTROL_PLANE_HOSTS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Storefront behaviour
    |--------------------------------------------------------------------------
    */

    'currency' => env('STORE_CURRENCY', 'IRR'),

    'locales' => ['fa'],

    'per_page' => [
        'products' => (int) env('STORE_PER_PAGE_PRODUCTS', 20),
        'blog' => (int) env('STORE_PER_PAGE_BLOG', 10),
        'vendors' => (int) env('STORE_PER_PAGE_VENDORS', 12),
        'admin' => (int) env('STORE_PER_PAGE_ADMIN', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    |
    | The placeholder gateway records an intent and returns to the storefront
    | without charging anything. Swap `driver` when a real gateway is wired.
    |
    */

    'payment' => [
        'driver' => env('STORE_PAYMENT_DRIVER', 'placeholder'),
        'callback_route' => 'checkout.callback',
    ],

];
