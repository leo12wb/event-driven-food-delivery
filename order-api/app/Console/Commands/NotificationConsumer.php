<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Jobcloud\Kafka\Consumer\KafkaConsumerBuilder;
use Jobcloud\Kafka\Exception\KafkaConsumerEndOfPartitionException;
use Jobcloud\Kafka\Exception\KafkaConsumerTimeoutException;

class NotificationConsumer extends Command
{
    protected $signature   = 'consumer:notification';
    protected $description = 'Notificação — escuta order.delivered e notifica o cliente';

    public function handle(): void
    {
        $brokers = config('kafka.brokers', 'kafka:9092');

        $consumer = KafkaConsumerBuilder::create()
            ->withAdditionalBroker($brokers)
            ->withConsumerGroup(config('kafka.consumer_groups.notification'))
            ->withSubscription('order.delivered')
            ->build();

        $consumer->subscribe();

        $this->info('[Notification] Aguardando entregas em order.delivered...');

        while (true) {
            try {
                $message = $consumer->consume(10000);

                if ($message->getOffset() < 0) {
                    continue;
                }

                $payload = json_decode($message->getBody(), true);
                $orderId = $payload['order_id'];

                $notification = "Pedido #{$orderId} entregue. Obrigado pela compra!";

                Log::info($notification);
                $this->info("[Notification] {$notification}");

                $consumer->commit($message);

            } catch (KafkaConsumerEndOfPartitionException | KafkaConsumerTimeoutException) {
                // nenhuma mensagem disponível
            } catch (\Throwable $e) {
                $this->error("[Notification] Erro: {$e->getMessage()}");
                sleep(2);
            }
        }
    }
}
