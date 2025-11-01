<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Ini konfigurasi untuk mengatur domain mana yang diizinkan mengakses API.
    | Pastikan hanya origin tertentu yang diizinkan agar aman dari permintaan liar.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5050',
        'http://localhost:5173',
        'https://simkeu.uiidalwa.web.id',
        'https://simkeuv2.uiidalwa.web.id',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => true,
];
