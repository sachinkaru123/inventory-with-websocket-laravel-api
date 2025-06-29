<?php

return [
    'paths' => ['api/*', 'broadcasting/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://192.168.8.111:3000', // Your local IP for network access
        'http://192.168.8.111:8000', // Laravel backend on local IP
    ],
    'allowed_origins_patterns' => [
        // Allow any localhost port for development
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',
        // Allow local network IPs (adjust pattern for your network)
        '/^http:\/\/192\.168\.\d{1,3}\.\d{1,3}:\d+$/',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Enable for authentication with cookies/sessions
];
