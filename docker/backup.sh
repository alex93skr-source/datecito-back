#!/usr/bin/env bash
# Respaldo diario de MySQL (lo instala scripts/deploy.sh en el cron del servidor).
# Guarda /srv/backups/datecito-AAAA-MM-DD.sql.gz y conserva 14 días.
set -euo pipefail
cd /srv/datecito/backend
mkdir -p /srv/backups
FILE="/srv/backups/datecito-$(date +%F).sql.gz"
docker compose -f docker-compose.prod.yml exec -T mysql sh -c 'mysqldump --single-transaction --quick -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' | gzip > "$FILE"
find /srv/backups -name 'datecito-*.sql.gz' -mtime +14 -delete
echo "$(date -Is) respaldo ok: $FILE ($(du -h "$FILE" | cut -f1))"
