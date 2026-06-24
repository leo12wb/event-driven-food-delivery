<?php

namespace App\Services;

use Jobcloud\Kafka\Message\KafkaProducerMessage;
use Jobcloud\Kafka\Producer\KafkaProducerBuilder;

class KafkaProducerService
{
    private mixed $producer;

    public function __construct()
    {
        $brokers = config('kafka.brokers', 'kafka:9092');

        $this->producer = KafkaProducerBuilder::create()
            ->withAdditionalBroker($brokers)
            ->build();
    }

    public function publish(string $topic, array $payload): void
    {
        $message = KafkaProducerMessage::create($topic, RD_KAFKA_PARTITION_UA)
            ->withBody(json_encode($payload));

        $this->producer->produce($message);
        $this->producer->flush(10000);
    }
}
