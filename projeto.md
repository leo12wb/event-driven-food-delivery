MVP — Clone Simplificado do Uber Eats
Objetivo

Demonstrar:

Event Driven Architecture
Kafka
Producers
Consumers
Comunicação assíncrona
Microserviços simplificados
Arquitetura
Cliente
   │
   ▼

Order API (Laravel)

POST /orders

   │
   ▼

Kafka
Topic: order.created

   │
   ▼

Kitchen Consumer

Pedido em preparo

   │
   ▼

Kafka
Topic: order.ready

   │
   ▼

Delivery Consumer

Sai para entrega

   │
   ▼

Kafka
Topic: order.delivered

   │
   ▼

Notification Consumer

Atualiza cliente
Stack
API
PHP 8.2
Laravel 12
MariaDB
Mensageria
Apache Kafka
Zookeeper
Docker
Biblioteca PHP Kafka
composer require jobcloud/php-kafka-lib
Serviços
1. Order API

Responsável por:

Criar pedidos
Persistir no banco
Publicar eventos
Tabela
orders

id
customer_name
total
status
created_at

Status:

created
preparing
ready
delivered
Endpoint
POST /api/orders

Request:

{
  "customer_name": "João",
  "total": 89.90
}

Resposta:

{
  "id": 1,
  "status": "created"
}
Producer

Após salvar:

$order = Order::create([...]);

$producer->send([
    'event' => 'order.created',
    'order_id' => $order->id
]);
Kafka Topics
order.created
order.ready
order.delivered
2. Kitchen Consumer

Escuta:

order.created

Fluxo:

Pedido recebido

↓

Preparando

↓

Pronto

Consumer:

while (true) {

    $message = $consumer->consume();

    $orderId = $message['order_id'];

    Order::find($orderId)
        ->update([
            'status' => 'ready'
        ]);

    $producer->send([
        'event' => 'order.ready',
        'order_id' => $orderId
    ]);
}
3. Delivery Consumer

Escuta:

order.ready

Fluxo:

Recebe pedido

↓

Sai para entrega

↓

Entregue

Consumer:

Order::find($id)
    ->update([
        'status' => 'delivered'
    ]);

Publica:

$producer->send([
    'event' => 'order.delivered',
    'order_id' => $id
]);
4. Notification Consumer

Escuta:

order.delivered

Simula:

Pedido #1 entregue.
Obrigado pela compra.

Pode enviar:

Email
Log
Notificação WebSocket

No MVP:

Log::info(
    "Pedido {$id} entregue."
);
Estrutura do Projeto
uber-eats-mvp/

├── order-api
│
├── kitchen-consumer
│
├── delivery-consumer
│
├── notification-consumer
│
├── docker
│
└── docker-compose.yml
Docker Compose
services:

  app:
    build: .

  mysql:
    image: mysql:8

  kafka:
    image: bitnami/kafka

  zookeeper:
    image: bitnami/zookeeper
Fluxo Completo
POST /orders

Pedido #1

Status:
created

        ↓

Kafka

order.created

        ↓

Kitchen Consumer

Status:
ready

        ↓

Kafka

order.ready

        ↓

Delivery Consumer

Status:
delivered

        ↓

Kafka

order.delivered

        ↓

Notification Consumer

Cliente notificado
Dashboard (Opcional)

Tela simples:

Pedido #1

✔ Criado

✔ Em preparo

✔ Pronto

✔ Entregue

Atualização via:

Polling
WebSocket (extra)
Banco
orders

Somente isso.

id
customer_name
total
status
created_at
updated_at
O que impressiona no currículo
Backend Júnior
APIs REST
Laravel
MySQL
Backend Pleno
Kafka
Event Driven
Consumers
Mensageria
Backend Sênior
Microservices
Async Processing
Arquitetura Distribuída