<?php

return [

    'paths' => ['*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // 'allowed_origins' => ['http://localhost:5173', 'http://192.168.1.17:5173', 'https://los.kspdjaya.id', 'https://dapur.kspdjaya.id'],
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
