#!/usr/bin/env bash
# NEXVARY CX Cloud — Laravel app container entrypoint
set -euo pipefail

APP_DIR='/var/www/nexvary-cx'

# ── Wait for PostgreSQL ───────────────────────────────────────────────────────
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
printf '[APP] Waiting for PostgreSQL at %s:%s...\n' "$DB_HOST" "$DB_PORT"
for i in $(seq 1 30); do
  if php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE:-nexvary_cx}','${DB_USERNAME:-nexvary_cx}','${DB_PASSWORD:-}');" 2>/dev/null; then
    printf '[APP] PostgreSQL ready\n'
    break
  fi
  if [[ $i -eq 30 ]]; then
    printf '[APP] PostgreSQL did not become ready after 30s — aborting\n' >&2
    exit 1
  fi
  sleep 1
done

# ── First-run initialisation ──────────────────────────────────────────────────
if [[ ! -f "${APP_DIR}/storage/.nexvary-initialized" ]]; then
  printf '[APP] First-run initialization...\n'

  # Run database migrations
  php "${APP_DIR}/artisan" migrate --force --no-interaction
  printf '[APP] Migrations complete\n'

  # Warm caches
  php "${APP_DIR}/artisan" config:cache
  php "${APP_DIR}/artisan" route:cache
  php "${APP_DIR}/artisan" view:cache
  printf '[APP] Caches warmed\n'

  touch "${APP_DIR}/storage/.nexvary-initialized"
else
  # Idempotent: run pending migrations on restart/upgrade
  php "${APP_DIR}/artisan" migrate --force --no-interaction
  php "${APP_DIR}/artisan" config:cache
fi

printf '[APP] Starting PHP-FPM...\n'
exec "$@"
