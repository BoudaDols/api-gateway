<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Driver
    |--------------------------------------------------------------------------
    | Supported: "log", "twilio", "vonage", "aws_sns"
    |
    | Use "log" for local development — OTPs are written to
    | storage/logs/laravel.log instead of sending real SMS.
    | Switch to a real driver by changing SMS_DRIVER in .env.
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Twilio
    |--------------------------------------------------------------------------
    | https://console.twilio.com
    | Requires: Account SID, Auth Token, and a Twilio phone number.
    */

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vonage (formerly Nexmo)
    |--------------------------------------------------------------------------
    | https://dashboard.nexmo.com
    | Requires: API Key, API Secret, and a sender name or number.
    */

    'vonage' => [
        'key'    => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'from'   => env('VONAGE_FROM', 'APIGateway'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS SNS
    |--------------------------------------------------------------------------
    | Uses existing AWS credentials from your environment.
    | IAM user needs sns:Publish permission.
    */

    'aws_sns' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
