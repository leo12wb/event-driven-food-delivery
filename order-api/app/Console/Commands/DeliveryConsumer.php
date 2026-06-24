<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\KafkaProducerService;
use Illuminate\Console\Command;
use Jobcloud\Kafka\Consumer\KafkaConsumerBuilder;
use Jobcloud\Kafka\Exception\KafkaConsumerEndOfPartitionException;
use Jobcloud\Kafka\Exception\KafkaConsumerTimeoutException;

class DeliveryConsumer extends Command
{
    protected $signature   = 'consumer:delivery';
    protected $description = 'Entrega — escuta order.ready e publica order.delivered';

    public function handle(): void
    {
        $brokers = config('kafka.brokers', 'kafka:9092');

        $consumer = KafkaConsumerBuilder::create()
            ->withAdditionalBroker($brokers)
            ->withConsumerGroup(config('kafka.consumer_groups.delivery'))
            ->withSubscription('order.ready')
            ->build();

        $consumer->subscribe();

        $this->info('[Delivery] Aguardando pedidos prontos em order.ready...');

        while (true) {
            try {
                $message = $consumer->consume(10000);

                if ($message->getOffset() < 0) {
                    continue;
                }

                $payload = json_decode($message->getBody(), true);
                $orderId = $payload['order_id'];

                $this->info("[Delivery] Pedido #{$orderId} recebido. Saindo para entrega...");

                $order = Order::find($orderId);

                if (! $order) {
                    $this->warn("[Delivery] Pedido #{$orderId} não encontrado no banco.");
                    $consumer->commit($message);
                    continue;
                }

                sleep(2); // simula tempo de deslocamento

                $order->update(['status' => Order::STATUS_DELIVERED]);
                $this->line("[Delivery]   Status → delivered");

                $producer = new KafkaProducerService();
                $producer->publish('order.delivered', [
                    'event'    => 'order.delivered',
                    'order_id' => $orderId,
                ]);

                $this->info("[Delivery] Pedido #{$orderId} entregue! Evento order.delivered publicado.");

                $consumer->commit($message);

            } catch (KafkaConsumerEndOfPartitionException | KafkaConsumerTimeoutException) {
                // nenhuma mensagem disponível
            } catch (\Throwable $e) {
                $this->error("[Delivery] Erro: {$e->getMessage()}");
                sleep(2);
            }
        }
    }
}
