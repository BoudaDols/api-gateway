<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposed_headers' => ['Set-Cookie'],
    'max_age' => 3600,
    'supports_credentials' => true,
];
