# Event-Driven Food Delivery API

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Kafka](https://img.shields.io/badge/Apache_Kafka-3.6-231F20?style=flat-square&logo=apachekafka&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.11-003545?style=flat-square&logo=mariadb&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)

A simplified Uber Eats clone built to demonstrate **Event-Driven Architecture** using Apache Kafka, Laravel 12, and Docker. Orders flow asynchronously through independent consumers — kitchen, delivery, and notification — each reacting to Kafka events without tight coupling.

---

## Architecture

```
Client
  │
  ▼
┌─────────────────────────┐
│   Order API (Laravel)   │  POST /api/orders
│   nginx + php-fpm       │────────────────────────────────────┐
└─────────────────────────┘                                     │
         │                                                      │
         │ Publishes                                            ▼
         ▼                                             ┌──────────────┐
  ┌─────────────┐                                      │   MariaDB    │
  │    Kafka    │  topic: order.created                │   orders     │
  └──────┬──────┘                                      └──────────────┘
         │
         ▼
┌─────────────────────┐
│   Kitchen Consumer  │  status: created → preparing → ready
└──────────┬──────────┘
           │ Publishes: order.ready
           ▼
  ┌─────────────┐
  │    Kafka    │  topic: order.ready
  └──────┬──────┘
         │
         ▼
┌──────────────────────┐
│  Delivery Consumer   │  status: ready → delivered
└──────────┬───────────┘
           │ Publishes: order.delivered
           ▼
  ┌─────────────┐
  │    Kafka    │  topic: order.delivered
  └──────┬──────┘
         │
         ▼
┌────────────────────────┐
│ Notification Consumer  │  logs: "Order #1 delivered. Thank you!"
└────────────────────────┘
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| API | Laravel 12, PHP 8.2 |
| Web Server | Nginx + PHP-FPM |
| Database | MariaDB 10.11 |
| Message Broker | Apache Kafka 3.6 |
| Coordination | Apache Zookeeper 3.8 |
| Kafka PHP Client | `jobcloud/php-kafka-lib` |
| Containerization | Docker + Docker Compose |

---

## Features

- **REST API** to create and track food orders
- **Kafka Producer** publishes events after each order state change
- **3 independent consumers** running as long-lived Artisan commands
- **Automatic setup** — `entrypoint.sh` handles `composer install`, key generation, and migrations on first boot
- **Shared vendor volume** so containers don't duplicate dependency installs
- **Health checks** ensure consumers only start after the API container is fully ready

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (with Compose v2)
- `make` (optional — all commands also work with plain `docker compose`)

---

## Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/YOUR_USERNAME/event-driven-food-delivery.git
cd event-driven-food-delivery

# 2. Start all services
docker compose up -d

# 3. Wait ~60s for the first boot (Composer install + migrations run automatically)
docker compose logs -f app
```

> On first boot the `app` container installs Composer dependencies, generates `APP_KEY`, and runs migrations automatically. Consumers wait for this to complete before starting.

---

## API Reference

### Create an Order

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"customer_name": "John Doe", "total": 89.90}'
```

**Response `201 Created`:**
```json
{
  "id": 1,
  "status": "created"
}
```

---

### List Orders

```bash
curl http://localhost:8000/api/orders \
  -H "Accept: application/json"
```

---

### Get Order by ID

```bash
curl http://localhost:8000/api/orders/1 \
  -H "Accept: application/json"
```

**Response:**
```json
{
  "id": 1,
  "customer_name": "John Doe",
  "total": "89.90",
  "status": "delivered",
  "created_at": "2025-01-01T12:00:00.000000Z",
  "updated_at": "2025-01-01T12:00:07.000000Z"
}
```

---

## Order Lifecycle

| Status | Triggered by |
|---|---|
| `created` | `POST /api/orders` |
| `preparing` | Kitchen Consumer receives `order.created` |
| `ready` | Kitchen Consumer finishes preparation |
| `delivered` | Delivery Consumer receives `order.ready` |

---

## Event Flow in Practice

Once you create an order, watch it move through the entire pipeline automatically:

```bash
# Terminal 1 — watch Kitchen Consumer
docker compose logs -f kitchen-consumer

# Terminal 2 — watch Delivery Consumer
docker compose logs -f delivery-consumer

# Terminal 3 — watch Notification Consumer
docker compose logs -f notification-consumer
```

Example output:

```
kitchen-consumer    | [Kitchen] Order #1 received. Starting preparation...
kitchen-consumer    | [Kitchen]   Status → preparing
kitchen-consumer    | [Kitchen]   Status → ready
kitchen-consumer    | [Kitchen] Order #1 ready! Event order.ready published.

delivery-consumer   | [Delivery] Order #1 received. Out for delivery...
delivery-consumer   | [Delivery]   Status → delivered
delivery-consumer   | [Delivery] Order #1 delivered! Event order.delivered published.

notification-consumer | [Notification] Order #1 delivered. Thank you for your purchase!
```

---

## Makefile Commands

```bash
make up               # Start all services in background
make down             # Stop and remove containers
make build            # Rebuild all images (no cache)
make logs             # Stream all logs
make shell            # Open a shell in the app container
make migrate          # Run migrations manually
make order            # Create a test order via curl
make status           # List all orders via curl
make logs-kitchen     # Stream Kitchen Consumer logs
make logs-delivery    # Stream Delivery Consumer logs
make logs-notification # Stream Notification Consumer logs
```

---

## Project Structure

```
.
├── docker-compose.yml
├── Makefile
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       └── php.ini
└── order-api/                          # Laravel 12 application
    ├── Dockerfile                      # PHP 8.2-fpm + rdkafka extension
    ├── entrypoint.sh                   # Boot script (install, migrate, run)
    ├── app/
    │   ├── Http/Controllers/
    │   │   └── OrderController.php     # POST /api/orders
    │   ├── Models/
    │   │   └── Order.php
    │   ├── Services/
    │   │   └── KafkaProducerService.php
    │   └── Console/Commands/
    │       ├── KitchenConsumer.php     # php artisan consumer:kitchen
    │       ├── DeliveryConsumer.php    # php artisan consumer:delivery
    │       └── NotificationConsumer.php # php artisan consumer:notification
    ├── config/
    │   └── kafka.php
    ├── database/migrations/
    │   └── ...create_orders_table.php
    └── routes/
        └── api.php
```

---

## Docker Services

| Service | Image | Port | Role |
|---|---|---|---|
| `nginx` | nginx:alpine | `8000` | HTTP reverse proxy |
| `app` | Custom PHP 8.2 | — | Laravel API (php-fpm) |
| `mariadb` | mariadb:10.11 | `3306` | Database |
| `zookeeper` | confluentinc/cp-zookeeper:7.6.0 | `2181` | Kafka coordination |
| `kafka` | confluentinc/cp-kafka:7.6.0 | `9092` | Message broker |
| `kafka-init` | confluentinc/cp-kafka:7.6.0 | — | Creates Kafka topics |
| `kitchen-consumer` | Custom PHP 8.2 | — | Processes `order.created` |
| `delivery-consumer` | Custom PHP 8.2 | — | Processes `order.ready` |
| `notification-consumer` | Custom PHP 8.2 | — | Processes `order.delivered` |

---

## Kafka Topics

| Topic | Producer | Consumer |
|---|---|---|
| `order.created` | Order API | Kitchen Consumer |
| `order.ready` | Kitchen Consumer | Delivery Consumer |
| `order.delivered` | Delivery Consumer | Notification Consumer |


## License

MIT
