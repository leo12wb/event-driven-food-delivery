<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\KafkaProducerService;
use Illuminate\Console\Command;
use Jobcloud\Kafka\Consumer\KafkaConsumerBuilder;
use Jobcloud\Kafka\Exception\KafkaConsumerEndOfPartitionException;
use Jobcloud\Kafka\Exception\KafkaConsumerTimeoutException;

class KitchenConsumer extends Command
{
    protected $signature   = 'consumer:kitchen';
    protected $description = 'Cozinha — escuta order.created e publica order.ready';

    public function handle(): void
    {
        $brokers = config('kafka.brokers', 'kafka:9092');

        $consumer = KafkaConsumerBuilder::create()
            ->withAdditionalBroker($brokers)
            ->withConsumerGroup(config('kafka.consumer_groups.kitchen'))
            ->withSubscription('order.created')
            ->build();

        $consumer->subscribe();

        $this->info('[Kitchen] Aguardando pedidos em order.created...');

        while (true) {
            try {
                $message = $consumer->consume(10000);

                if ($message->getOffset() < 0) {
                    continue;
                }

                $payload = json_decode($message->getBody(), true);
                $orderId = $payload['order_id'];

                $this->info("[Kitchen] Pedido #{$orderId} recebido. Iniciando preparo...");

                $order = Order::find($orderId);

                if (! $order) {
                    $this->warn("[Kitchen] Pedido #{$orderId} não encontrado no banco.");
                    $consumer->commit($message);
                    continue;
                }

                $order->update(['status' => Order::STATUS_PREPARING]);
                $this->line("[Kitchen]   Status → preparing");

                sleep(3); // simula tempo de preparo

                $order->update(['status' => Order::STATUS_READY]);
                $this->line("[Kitchen]   Status → ready");

                $producer = new KafkaProducerService();
                $producer->publish('order.ready', [
                    'event'    => 'order.ready',
                    'order_id' => $orderId,
                ]);

                $this->info("[Kitchen] Pedido #{$orderId} pronto! Evento order.ready publicado.");

                $consumer->commit($message);

            } catch (KafkaConsumerEndOfPartitionException | KafkaConsumerTimeoutException) {
                // nenhuma mensagem disponível — aguarda próximo ciclo
            } catch (\Throwable $e) {
                $this->error("[Kitchen] Erro: {$e->getMessage()}");
                sleep(2);
            }
        }
    }
}
