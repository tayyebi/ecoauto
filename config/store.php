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
    | HTTP binding
    |--------------------------------------------------------------------------
    |
    | The port lives here, not in the serve command signature, so the console
    | summaries print the truth instead of a stale literal. The deploy target
    | publishes one port and expects 9020 on it.
    |
    */

    'serve' => [
        // DECISION (infra): bind 0.0.0.0 inside the container. The port is
        // published on the host by docker-compose, so anything narrower than
        // 0.0.0.0 here would make the published port refuse connections.
        'host' => env('STORE_HOST', '0.0.0.0'),

        // DECISION (infra): 9020 is the canonical port and it is the same
        // number on both sides - container and host. One number to set, one
        // number to remember, and no `80:9020` style translation to misread.
        'port' => (int) env('STORE_PORT', 9020),
    ],

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    |
    | Deployment is `git pull` and `docker-compose up -d`, so the entrypoint
    | has to survive the database container not being ready yet.
    |
    */

    'boot' => [
        'db_wait_attempts' => (int) env('STORE_DB_WAIT_ATTEMPTS', 30),
        'db_wait_seconds' => (int) env('STORE_DB_WAIT_SECONDS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | First operator
    |--------------------------------------------------------------------------
    |
    | Who owns the installation when it is seeded. A deploy that cannot choose
    | its own administrator identity ends up needing a database reset on every
    | environment, so this is configuration rather than a literal in a seeder.
    |
    */

    'operator' => [
        'email' => env('STORE_ADMIN_EMAIL', 'tayyebimohammadreza@gmail.com'),

        // DECISION (security): a known default password is a deliberate
        // first-boot convenience, not a shipping credential. bin/bootstrap
        // prints a warning and the admin must change it on first login.
        'password' => env('STORE_ADMIN_PASSWORD', 'password'),
    ],

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
