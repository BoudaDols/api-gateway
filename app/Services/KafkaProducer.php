<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;
use ReflectionClass;

class KafkaProducer
{
    private ?Producer $producer = null;

    /**
     * Publish an event to a Kafka topic.
     *
     * @param string $topic   Kafka topic name
     * @param array  $payload Event payload (will be JSON-encoded)
     * @param string $key     Optional partition key (e.g. user_id for ordering)
     */
    public function publish(string $topic, array $payload, string $key = ''): void
    {
        if (! config('kafka.enabled')) {
            return;
        }

        try {
            $this->getProducer()->send(
                $topic,
                json_encode($payload),
                $key !== '' ? $key : null
            );
        } catch (\Throwable $e) {
            // Kafka failures must never break the main request flow.
            Log::warning("[KafkaProducer] Failed to publish to {$topic}: " . $e->getMessage());
        }
    }

    private function getProducer(): Producer
    {
        if ($this->producer === null) {
            $brokers = config('kafka.brokers');

            $config = new ProducerConfig();
            $config->setBrokers($brokers);
            $config->setAcks(0);
            $config->setAutoCreateTopic(true);
            $config->setUpdateBrokers(false);

            $this->producer = new Producer($config);

            // Workaround: longlang/phpkafka with Kafka 3.7+ KRaft mode
            // The library stores brokers indexed by position (0, 1, 2...)
            // but topic metadata references them by broker ID (1).
            // Manually register the bootstrap broker with ID 1.
            $broker = $this->producer->getBroker();
            $reflection = new ReflectionClass($broker);
            $prop = $reflection->getProperty('brokers');
            $prop->setAccessible(true);
            $prop->setValue($broker, [1 => $brokers]);
        }

        return $this->producer;
    }
}
