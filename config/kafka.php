<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    |
    | Kafka broker address and feature toggle. Set KAFKA_ENABLED=false
    | to disable event publishing (useful for testing).
    |
    */

    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'enabled' => env('KAFKA_ENABLED', true),

];
