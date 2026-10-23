#!/usr/bin/env bash
# Se ejecuta EN EL SERVIDOR (lo invoca scripts/deploy.sh por SSH): arranca los contenedores,
# instala dependencias, migra la base y deja la API lista. Idempotente: se puede repetir.
#   bash docker/remote-deploy.sh <dominio> <correo-letsencrypt>
set -euo pipefail
DOMAIN="${1:?dominio}"; ACME_EMAIL="${2:?correo}"
cd /srv/datecito/backend
COMPOSE=(docker compose -f docker-compose.prod.yml)
ART=("${COMPOSE[@]}" exec -T app php artisan)

mkdir -p storage/logs storage/framework/{cache/data,sessions,views} bootstrap/cache

if [ ! -f .env ]; then
  echo "Primera vez: creando .env con secretos aleatorios"
  awk -v d="$DOMAIN" -v e="$ACME_EMAIL" '
    { while (index($0, "__GEN__")) { cmd = "openssl rand -hex 24"; cmd | getline s; close(cmd); sub("__GEN__", s) }
      gsub("__DOMAIN__", d); gsub("__ACME_EMAIL__", e); print }' .env.production.example > .env
  chmod 600 .env
fi

echo "Arrancando contenedores"
"${COMPOSE[@]}" up -d --build --remove-orphans </dev/null
for _ in $(seq 1 30); do "${COMPOSE[@]}" exec -T app php -v >/dev/null 2>&1 && break; sleep 2; done

echo "Instalando dependencias PHP"
"${COMPOSE[@]}" exec -T app composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

if grep -q '^APP_KEY=$' .env; then
  echo "Generando APP_KEY"
  "${ART[@]}" key:generate --force --no-interaction
fi

"${COMPOSE[@]}" exec -T app chown -R www-data:www-data storage bootstrap/cache

echo "Migrando base de datos"
"${ART[@]}" migrate --force --no-interaction
"${ART[@]}" datecito:storage-setup --no-interaction || echo "(el bucket se creará en el siguiente despliegue)"

echo "Optimizando y reiniciando colas"
"${ART[@]}" optimize --no-interaction
"${ART[@]}" queue:restart --no-interaction
"${COMPOSE[@]}" restart queue scheduler >/dev/null
# El Caddyfile está montado por inodo y el tar lo reemplaza por otro archivo: hay que recrear el contenedor para que lo vea.
"${COMPOSE[@]}" up -d --force-recreate caddy >/dev/null 2>&1

# Respaldo diario de la base de datos a las 3:30 (hora del servidor), 14 días de historial.
install -m 755 docker/backup.sh /usr/local/bin/datecito-backup
( crontab -l 2>/dev/null | grep -v datecito-backup; echo "30 3 * * * /usr/local/bin/datecito-backup >> /var/log/datecito-backup.log 2>&1" ) | crontab -

echo
"${COMPOSE[@]}" ps --format '{{.Service}}: {{.Status}}'
