<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    /**
     * Send an SMS message to the given phone number.
     * Driver is selected from config('sms.driver').
     */
    public function send(string $phone, string $message): void
    {
        match (config('sms.driver')) {
            'log' => $this->sendViaLog($phone, $message),
            'twilio' => $this->sendViaTwilio($phone, $message),
            'vonage' => $this->sendViaVonage($phone, $message),
            'aws_sns' => $this->sendViaAwsSns($phone, $message),
            default => throw new RuntimeException('Unsupported SMS driver: '.config('sms.driver')),
        };
    }

    /**
     * Log driver — writes SMS to Laravel log.
     * Use for local development. Read OTP from storage/logs/laravel.log.
     */
    private function sendViaLog(string $phone, string $message): void
    {
        Log::info('[SMS] To: '.$phone.' | Message: '.$message);
    }

    /**
     * Twilio driver.
     * Requires: TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM in .env
     * Install SDK: composer require twilio/sdk
     */
    private function sendViaTwilio(string $phone, string $message): void
    {
        $sid = config('sms.twilio.sid');
        $token = config('sms.twilio.token');
        $from = config('sms.twilio.from');

        if (! $sid || ! $token || ! $from) {
            throw new RuntimeException('Twilio credentials not configured. Set TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM in .env');
        }

        // Uncomment after running: composer require twilio/sdk
        // $client = new \Twilio\Rest\Client($sid, $token);
        // $client->messages->create($phone, ['from' => $from, 'body' => $message]);
        throw new RuntimeException('Twilio driver not yet activated. Run: composer require twilio/sdk and uncomment the code in SmsService.');
    }

    /**
     * Vonage driver.
     * Requires: VONAGE_KEY, VONAGE_SECRET, VONAGE_FROM in .env
     * Install SDK: composer require vonage/client
     */
    private function sendViaVonage(string $phone, string $message): void
    {
        $key = config('sms.vonage.key');
        $secret = config('sms.vonage.secret');
        $from = config('sms.vonage.from');

        if (! $key || ! $secret) {
            throw new RuntimeException('Vonage credentials not configured. Set VONAGE_KEY, VONAGE_SECRET in .env');
        }

        // Uncomment after running: composer require vonage/client
        // $client = new \Vonage\Client(new \Vonage\Client\Credentials\Basic($key, $secret));
        // $client->sms()->send(new \Vonage\SMS\Message\SMS($phone, $from, $message));
        throw new RuntimeException('Vonage driver not yet activated. Run: composer require vonage/client and uncomment the code in SmsService.');
    }

    /**
     * AWS SNS driver.
     * Requires: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION in .env
     * IAM user needs sns:Publish permission.
     * Install SDK: composer require aws/aws-sdk-php
     */
    private function sendViaAwsSns(string $phone, string $message): void
    {
        $key = config('sms.aws_sns.key');
        $secret = config('sms.aws_sns.secret');
        $region = config('sms.aws_sns.region');

        if (! $key || ! $secret) {
            throw new RuntimeException('AWS credentials not configured. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY in .env');
        }

        // Uncomment after running: composer require aws/aws-sdk-php
        // $client = new \Aws\Sns\SnsClient([
        //     'version'     => 'latest',
        //     'region'      => $region,
        //     'credentials' => ['key' => $key, 'secret' => $secret],
        // ]);
        // $client->publish(['Message' => $message, 'PhoneNumber' => $phone]);
        throw new RuntimeException('AWS SNS driver not yet activated. Run: composer require aws/aws-sdk-php and uncomment the code in SmsService.');
    }
}
