# Clone Simplificado do Uber Eats — Guia em Português

API de delivery com arquitetura orientada a eventos usando Laravel 12, Apache Kafka e Docker.

---

## Subindo o projeto

```bash
docker compose up -d --build
```

No primeiro start aguarde ~2 minutos: o container instala as dependências Composer, gera a `APP_KEY` e roda as migrations automaticamente.

Para acompanhar a inicialização:

```bash
docker compose logs -f app
```

Quando aparecer `ready to handle connections`, a API está no ar.

---

## Endpoints — `http://localhost:8000`

### Criar um pedido

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"customer_name": "Leonardo", "total": 49.90}'
```

Resposta `201`:
```json
{ "id": 1, "status": "created" }
```

### Listar todos os pedidos

```bash
curl http://localhost:8000/api/orders
```

### Ver um pedido específico

```bash
curl http://localhost:8000/api/orders/1
```

Resposta (após ~8 segundos do pedido criado):
```json
{
  "id": 1,
  "customer_name": "Leonardo",
  "total": "49.90",
  "status": "delivered",
  "created_at": "...",
  "updated_at": "..."
}
```

---

## O que acontece automaticamente

Ao criar um pedido, o Kafka dispara este fluxo de forma assíncrona:

```
POST /api/orders
      │
      │ publica evento
      ▼
  order.created ──► KitchenConsumer
                        │  status: created → preparing → ready
                        │ publica evento
                        ▼
                    order.ready ──► DeliveryConsumer
                                        │  status: ready → delivered
                                        │ publica evento
                                        ▼
                                    order.delivered ──► NotificationConsumer
                                                            │  loga a entrega
```

O controller só publica um evento e não sabe quem vai consumir. Cada consumer é um processo independente rodando em loop.

### Ciclo de vida do pedido

| Status | Quem define |
|---|---|
| `created` | `POST /api/orders` |
| `preparing` | KitchenConsumer recebe `order.created` |
| `ready` | KitchenConsumer termina o preparo |
| `delivered` | DeliveryConsumer recebe `order.ready` |

---

## Acompanhando os logs dos consumers

```bash
# Ver a cozinha processar
docker compose logs -f kitchen-consumer

# Ver a entrega processar
docker compose logs -f delivery-consumer

# Ver a notificação
docker compose logs -f notification-consumer
```

---

## Por que Kafka?

O cenário simulado tem **vários sistemas independentes** que precisam reagir ao mesmo evento:

```
Pedido criado
     │
     ├──► Cozinha  (precisa saber para preparar)
     ├──► Entrega  (precisa saber quando ficou pronto)
     └──► Notificação (precisa saber quando foi entregue)
```

### Sem Kafka — acoplamento direto

```php
// OrderController precisaria conhecer todos os serviços
$order->save();
$kitchen->notifyNewOrder($order);   // e se cair?
$delivery->schedulePickup($order);  // e se demorar?
$notification->sendEmail($order);   // e se falhar?
```

Se qualquer chamada falhar, o pedido fica em estado inconsistente. E qualquer novo sistema que precise saber de pedidos exige alteração no controller.

### Com Kafka — desacoplamento total

```php
// OrderController só publica e esquece
$this->kafka->publish('order.created', [...]);
```

A mensagem fica no tópico. Cada consumer lê no seu ritmo, pode cair e reiniciar (`restart: on-failure`), e o pedido nunca fica perdido.

### Benefícios concretos

| Benefício | Como aparece no projeto |
|---|---|
| **Desacoplamento** | Controller não conhece Kitchen, Delivery nem Notification |
| **Resiliência** | Consumer cai → reinicia e reprocessa do ponto onde parou |
| **Ordem garantida** | `created` → `ready` → `delivered` sempre nessa sequência |
| **Extensibilidade** | Novo consumer? Só assinar o tópico — zero mudança no controller |

### Quando Kafka seria exagero?

Para um projeto com um único servidor e fluxo simples, as **filas nativas do Laravel** (`queue:work` com banco ou Redis) resolveriam com muito menos complexidade. Kafka faz sentido quando há múltiplos serviços independentes (microserviços reais) ou volume alto de eventos — exatamente o padrão que o Uber real usa em produção.

---

## Parando o projeto

```bash
# Para os containers (mantém banco e vendor)
docker compose down

# Para E apaga tudo (reset completo)
docker compose down -v
```

---

## Serviços Docker

| Container | Imagem | Porta | Função |
|---|---|---|---|
| `uber_nginx` | nginx:alpine | `8000` | Proxy reverso HTTP |
| `uber_app` | PHP 8.2-fpm customizado | — | API Laravel (php-fpm) |
| `uber_mariadb` | mariadb:10.11 | `3306` | Banco de dados |
| `uber_zookeeper` | confluentinc/cp-zookeeper:7.6.0 | `2181` | Coordenação do Kafka |
| `uber_kafka` | confluentinc/cp-kafka:7.6.0 | `9092` | Message broker |
| `uber_kafka_init` | confluentinc/cp-kafka:7.6.0 | — | Cria os tópicos Kafka |
| `uber_kitchen` | PHP 8.2-fpm customizado | — | Consome `order.created` |
| `uber_delivery` | PHP 8.2-fpm customizado | — | Consome `order.ready` |
| `uber_notification` | PHP 8.2-fpm customizado | — | Consome `order.delivered` |

## Tópicos Kafka

| Tópico | Publicado por | Consumido por |
|---|---|---|
| `order.created` | Order API | KitchenConsumer |
| `order.ready` | KitchenConsumer | DeliveryConsumer |
| `order.delivered` | DeliveryConsumer | NotificationConsumer |
