<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    | The secret key used to sign JWT tokens. Falls back to APP_KEY if not set.
    */
    'secret' => env('JWT_SECRET', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | JWT Time To Live (TTL)
    |--------------------------------------------------------------------------
    | Token expiration time in minutes. Default is 60 minutes (1 hour).
    */
    'ttl' => env('JWT_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | JWT Refresh Time To Live
    |--------------------------------------------------------------------------
    | Refresh token expiration time in minutes. Default is 20160 (2 weeks).
    */
    'refresh_ttl' => env('JWT_REFRESH_TTL', 10080),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    | The algorithm used to sign the token. HS256 is the most common.
    */
    'algo' => 'HS256',

    /*
    |--------------------------------------------------------------------------
    | Refresh Token Cookie
    |--------------------------------------------------------------------------
    | Configuration for the httpOnly cookie used to store the refresh token.
    | The cookie is never accessible to JavaScript — the browser manages it.
    */
    'refresh_cookie' => [
        'name'      => env('JWT_REFRESH_COOKIE_NAME', 'refresh_token'),
        'path'      => '/api/auth',           // Only sent to auth endpoints
        'secure'    => env('JWT_COOKIE_SECURE', true),  // HTTPS only (false for local dev)
        'httponly'   => true,                  // Not accessible via JavaScript
        'samesite'  => 'Strict',              // No cross-site requests
    ],
];
