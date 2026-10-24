#!/bin/sh
set -eu

mkdir -p backups
BACKUP_FILE="backups/inventory-$(date +%Y%m%d-%H%M%S).sql"
COMPOSE_FILE="${COMPOSE_FILE:-compose.yaml}"
docker compose -f "$COMPOSE_FILE" exec -T mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' > "$BACKUP_FILE"
echo "Backup created: $BACKUP_FILE"
