# Production Deployment Guide

1. Copy `.env.production.example` to `.env.production`.
2. Generate an application key with `docker compose run --rm app php artisan key:generate --show`.
3. Replace every `CHANGE_ME` value and set the HTTPS public URL.
4. Put the application behind a hosting-provider HTTPS proxy or load balancer.
5. Run `./scripts/deploy.sh`.
6. Check `/api/health`, the queue logs, scheduler logs, and reporting `/health`.
7. Create and test a backup with `./scripts/backup-database.sh`.

For production backup and restore commands, set `COMPOSE_FILE=compose.production.yaml`.

Never commit `.env.production`, database dumps, real customer information, or access tokens.

## HTTPS

TLS certificates are intentionally managed outside the repository. Recommended options are a cloud load balancer, managed container platform, or Caddy/Traefik reverse proxy with automatic certificates.

## Restore drill

Restoration overwrites current database data and therefore requires explicit confirmation:

```bash
CONFIRM_RESTORE=YES ./scripts/restore-database.sh backups/inventory-TIMESTAMP.sql
```
