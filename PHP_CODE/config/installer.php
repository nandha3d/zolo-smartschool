<?php

/*
|--------------------------------------------------------------------------
| Installer requirements
|--------------------------------------------------------------------------
|
| Each 'check' is a declarative string resolved by
| dacoto\LaravelWizardInstaller\RequirementChecker. They were previously
| closures, which made this config array unserialisable and so broke
| `php artisan config:cache` for the entire application.
|
*/

return [
    'icon' => 'assets/horizontal-logo.svg',

    'support_url' => 'https://zoloschools.com/support',

    'server' => [
        'php'       => [
            'name'    => 'PHP Version',
            'version' => '>= 8.2.0',
            'check'   => 'php:8.2'
        ],
        'pdo'       => [
            'name'  => 'PDO',
            'check' => 'extension:pdo_mysql'
        ],
        'mbstring'  => [
            'name'  => 'Mbstring extension',
            'check' => 'extension:mbstring'
        ],
        'fileinfo'  => [
            'name'  => 'Fileinfo extension',
            'check' => 'extension:fileinfo'
        ],
        'openssl'   => [
            'name'  => 'OpenSSL extension',
            'check' => 'extension:openssl'
        ],
        'tokenizer' => [
            'name'  => 'Tokenizer extension',
            'check' => 'extension:tokenizer'
        ],
        'json'      => [
            'name'  => 'Json extension',
            'check' => 'extension:json'
        ],
        'curl'      => [
            'name'  => 'Curl extension',
            'check' => 'extension:curl'
        ],
        'zip'       => [
            'name'  => 'Zip extension',
            'check' => 'extension:zip'
        ],
        'gd'        => [
            'name'  => 'GD extension',
            'check' => 'extension:gd'
        ]
    ],

    'folders' => [
        'storage.framework' => [
            'name'  => base_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework',
            'check' => 'writable:storage/framework'
        ],
        'storage.logs'      => [
            'name'  => base_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            'check' => 'writable:storage/logs'
        ],
        'storage.cache'     => [
            'name'  => base_path() . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache',
            'check' => 'writable:bootstrap/cache'
        ],
    ],

    'database' => [
        'seeders' => false
    ],

    'commands' => [
        'db:seed --class=InstallationSeeder',
        'db:seed --class=AddSuperAdminSeeder',
    ],

    /*
    | The first administrator is created by AddSuperAdminSeeder with a randomly
    | generated password that is printed once during installation, so no default
    | credentials are stored here.
    */
];
