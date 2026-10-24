#!/bin/sh
set -eu

COMPOSE_FILE=compose.production.yaml

test -f .env.production || { echo "Create .env.production first."; exit 1; }
docker compose -f "$COMPOSE_FILE" build
docker compose -f "$COMPOSE_FILE" up -d mysql
docker compose -f "$COMPOSE_FILE" run --rm app php artisan migrate --force
docker compose -f "$COMPOSE_FILE" up -d
docker compose -f "$COMPOSE_FILE" ps
