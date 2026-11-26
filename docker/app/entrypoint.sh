#!/usr/bin/env bash
# NEXVARY CX Cloud — Laravel app container entrypoint
set -euo pipefail

APP_DIR='/var/www/nexvary-cx'
PUBLIC_VOL='/var/www/nexvary-cx-public'

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

# A pre-existing storage volume can hide directories from the image.
mkdir -p "${APP_DIR}/storage/framework/cache"   "${APP_DIR}/storage/framework/sessions" "${APP_DIR}/storage/framework/views"

# ── First-run initialisation ──────────────────────────────────────────────────
if [[ ! -f "${APP_DIR}/storage/.nexvary-initialized" ]]; then
  printf '[APP] First-run initialization...\n'

  php "${APP_DIR}/artisan" migrate --force --no-interaction
  printf '[APP] Migrations complete\n'

  php "${APP_DIR}/artisan" config:cache
  php "${APP_DIR}/artisan" route:cache
  php "${APP_DIR}/artisan" view:cache
  printf '[APP] Caches warmed\n'

  touch "${APP_DIR}/storage/.nexvary-initialized"
else
  php "${APP_DIR}/artisan" migrate --force --no-interaction
  php "${APP_DIR}/artisan" config:cache
fi

# ── Create storage symlink before publishing assets ───────────────────────────
# This creates public/storage -> ../storage/app/public so nginx can follow
# the symlink through the app_storage volume it also mounts.
php "${APP_DIR}/artisan" storage:link --force 2>/dev/null || true

# ── Publish static assets to shared volume ───────────────────────────────────
# nginx reads from this volume (mounted read-only on the nginx service).
# Always refresh so the volume matches this exact image build.
# Remove old files first to avoid stale Vite-hashed chunks accumulating.
printf '[APP] Publishing static assets to %s...\n' "$PUBLIC_VOL"
find "${PUBLIC_VOL}" -mindepth 1 -delete 2>/dev/null || true
cp -a "${APP_DIR}/public/." "${PUBLIC_VOL}/"
printf '[APP] Static assets published\n'

printf '[APP] Starting PHP-FPM...\n'
exec "$@"
