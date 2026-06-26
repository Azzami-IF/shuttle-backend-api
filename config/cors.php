<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://ambatu.my.id',
        'https://www.ambatu.my.id',
        'http://kemanapungo.ambatu.my.id',
        'https://kemanapungo.ambatu.my.id',
        'http://localhost:3000',
        'http://localhost:8100',
        'http://localhost:4200',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*', 'Authorization', 'Content-Type'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 0,

    'supports_credentials' => true,

];
