<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
|
| DECISION (tenancy): there are exactly two connection shapes. `landlord`
| holds the account tree and hostname registry; `tenant` is re-pointed at
| runtime and holds one storefront's content. Nothing else touches MySQL.
|
|--------------------------------------------------------------------------
|
| Two kinds of connection:
|
|   landlord  the account tree and hostname registry (one database)
|   tenant    swapped at runtime, one database per storefront account
|
| Both point at the MySQL server that lives under ./data/mysql, so the entire
| installation is a single directory you can copy, back up, or move.
|
*/

$dataPath = env('STORE_DATA_PATH', base_path('data'));

$mysql = [
    'driver' => 'mysql',
    'host' => env('STORE_DB_HOST', '127.0.0.1'),
    'port' => env('STORE_DB_PORT', '3306'),
    'username' => env('STORE_DB_USERNAME', 'store'),
    'password' => env('STORE_DB_PASSWORD', ''),

    /*
     | DECISION (db): `unix_socket` is only honoured when set explicitly, never
     | from a default. Laravel's MySqlConnector checks `hasSocket()` first and
     | builds a socket DSN when it is non-empty, which silently ignores host and
     | port. A defaulted socket path therefore made STORE_DB_HOST=db inert: the
     | app tried to connect to a socket file inside its own container and failed
     | forever while MariaDB was up and healthy on the network.
     */
    'unix_socket' => env('STORE_DB_SOCKET'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => 'InnoDB',
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
];

return [

    'default' => env('DB_CONNECTION', 'landlord'),

    'connections' => [

        'landlord' => array_merge($mysql, [
            'database' => env('STORE_DB_LANDLORD', 'store_landlord'),
        ]),

        /*
         | Rewritten per request by App\Tenancy\DatabaseManager. Kept here so
         | that `php artisan migrate --database=tenant` and queue workers have
         | a sane default shape before an account is resolved.
         */
        'tenant' => array_merge($mysql, [
            'database' => env('STORE_DB_TENANT', 'store_tenant'),
        ]),

    ],

    'migrations' => [
        'landlord' => [
            'path' => database_path('migrations/landlord'),
            'table' => 'migrations',
        ],
        'tenant' => [
            'path' => database_path('migrations/tenant'),
            'table' => 'migrations',
        ],
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('STORE_NAME', 'store')).'-database-'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],

];
