.PHONY: up down build logs shell migrate order status

# Sobe todos os serviços
up:
	docker compose up -d

# Para e remove containers
down:
	docker compose down

# Rebuild das imagens
build:
	docker compose build --no-cache

# Logs em tempo real
logs:
	docker compose logs -f

# Acessa o shell do app
shell:
	docker compose exec app sh

# Executa migrations manualmente
migrate:
	docker compose exec app php artisan migrate --force

# Cria um pedido de teste
order:
	curl -s -X POST http://localhost:8000/api/orders \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d '{"customer_name":"João Silva","total":89.90}' | python3 -m json.tool || \
	curl -s -X POST http://localhost:8000/api/orders \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d '{"customer_name":"João Silva","total":89.90}'

# Lista pedidos
status:
	curl -s http://localhost:8000/api/orders \
		-H "Accept: application/json" | python3 -m json.tool || \
	curl -s http://localhost:8000/api/orders -H "Accept: application/json"

# Logs por serviço
logs-kitchen:
	docker compose logs -f kitchen-consumer

logs-delivery:
	docker compose logs -f delivery-consumer

logs-notification:
	docker compose logs -f notification-consumer
