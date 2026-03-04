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
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    | The algorithm used to sign the token. HS256 is the most common.
    */
    'algo' => 'HS256',
];
