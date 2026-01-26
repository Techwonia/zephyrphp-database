<?php

/**
 * Database Configuration
 *
 * Configure your database connection and ORM settings here.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | Supported: "pdo_mysql", "pdo_pgsql", "pdo_sqlite", "pdo_sqlsrv"
    |
    */
    'connection' => env('DB_CONNECTION', 'pdo_mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'mysql' => [
            'driver' => 'pdo_mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'zephyrphp'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ],

        'pgsql' => [
            'driver' => 'pdo_pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'zephyrphp'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
        ],

        'sqlite' => [
            'driver' => 'pdo_sqlite',
            'path' => storage_path('database.sqlite'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Paths
    |--------------------------------------------------------------------------
    |
    | The paths where your entity classes are located.
    |
    */
    'paths' => [
        base_path('app/Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Mode
    |--------------------------------------------------------------------------
    |
    | When true, Doctrine will use array caching and will regenerate
    | proxies on every request. Set to false in production.
    |
    */
    'dev_mode' => env('APP_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Proxy Settings
    |--------------------------------------------------------------------------
    */
    'proxy_dir' => storage_path('proxies'),
    'proxy_namespace' => 'DoctrineProxies',

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'table' => 'migrations',
        'path' => base_path('database/migrations'),
    ],
];
