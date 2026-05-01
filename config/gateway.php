<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Service Registry
    |--------------------------------------------------------------------------
    | Automatically discovers any SERVICE_*_URL environment variable.
    | Adding SERVICE_ORDERS_URL=http://... to .env automatically
    | registers 'orders' as a service — no code changes needed.
    |
    | Usage: GET /api/services/orders/123
    |        → forwards to SERVICE_ORDERS_URL/123
    */

    'services' => collect($_ENV)
        ->filter(fn ($v, $k) => str_starts_with($k, 'SERVICE_') && str_ends_with($k, '_URL'))
        ->mapWithKeys(fn ($v, $k) => [
            strtolower(str_replace(['SERVICE_', '_URL'], '', $k)) => $v,
        ])
        ->all(),

    // Seconds to wait for a microservice response before timing out
    'timeout' => env('GATEWAY_TIMEOUT', 10),

];
