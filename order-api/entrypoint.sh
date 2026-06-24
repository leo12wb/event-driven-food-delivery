#!/bin/sh
set -e

WORKDIR=/var/www/html

# Garante estrutura de diretórios do Laravel
mkdir -p "$WORKDIR/storage/app/public" \
         "$WORKDIR/storage/framework/cache/data" \
         "$WORKDIR/storage/framework/sessions" \
         "$WORKDIR/storage/framework/views" \
         "$WORKDIR/storage/logs" \
         "$WORKDIR/bootstrap/cache"

chown -R www-data:www-data "$WORKDIR/storage" "$WORKDIR/bootstrap/cache" 2>/dev/null || true

# Copia .env se não existir
if [ ! -f "$WORKDIR/.env" ]; then
    cp "$WORKDIR/.env.example" "$WORKDIR/.env"
    echo ".env criado a partir do .env.example"
fi

# Instala dependências se vendor/ não existir
if [ ! -d "$WORKDIR/vendor" ]; then
    echo "Instalando dependências Composer..."
    cd "$WORKDIR" && composer install --no-interaction --optimize-autoloader
fi

# Gera APP_KEY se estiver vazio
APP_KEY_VALUE=$(grep "^APP_KEY=" "$WORKDIR/.env" | cut -d'=' -f2-)
if [ -z "$APP_KEY_VALUE" ]; then
    echo "Gerando APP_KEY..."
    cd "$WORKDIR" && php artisan key:generate --ansi
fi

# Aguarda banco de dados
echo "Aguardando MariaDB em ${DB_HOST:-mariadb}..."
until php -r "
    try {
        new PDO(
            'mysql:host=${DB_HOST:-mariadb};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-uber_eats}',
            '${DB_USERNAME:-uber}',
            '${DB_PASSWORD:-secret}'
        );
        echo 'OK';
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null | grep -q OK; do
    echo "  ...banco não disponível, aguardando 3s"
    sleep 3
done
echo "MariaDB pronto!"

# Executa migrations (idempotente)
cd "$WORKDIR" && php artisan migrate --force --no-interaction

echo "Iniciando: $*"
exec "$@"
