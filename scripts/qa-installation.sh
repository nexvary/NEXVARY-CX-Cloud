#!/usr/bin/env bash
set -euo pipefail

: "${QA_DB_PASSWORD:?A password for the isolated PostgreSQL role is required}"
export DB_HOST="${QA_DB_HOST:-127.0.0.1}"
export DB_PORT="${QA_DB_PORT:-5432}"
export DB_USERNAME="${QA_DB_USER:-cx}"
export REDIS_HOST="${QA_REDIS_HOST:-127.0.0.1}"
export REDIS_PORT="${QA_REDIS_PORT:-6379}"
export SESSION_SECURE_COOKIE=false
export APP_ENV=local APP_DEBUG=false APP_LOCALE=en DB_CONNECTION=pgsql
export QA_BASE_URL="http://127.0.0.1:${QA_HTTP_PORT:-18081}"
export APP_URL="$QA_BASE_URL"
export QA_SETUP_TOKEN
QA_SETUP_TOKEN="$(openssl rand -hex 32)"
export NEXVARY_INSTALL_SECRET="$QA_SETUP_TOKEN"
export QA_ADMIN_PASSWORD
QA_ADMIN_PASSWORD="$(openssl rand -hex 24)"
export APP_KEY
APP_KEY="base64:$(openssl rand -base64 32)"
export FREESWITCH_HOST="${QA_ESL_HOST:-127.0.0.1}"
export FREESWITCH_ESL_PORT="${QA_ESL_PORT:-18021}"
export FREESWITCH_ESL_PASSWORD="${QA_ESL_PASSWORD:-$(openssl rand -hex 24)}"

qa_root="$(mktemp -d)"
app_pid=''
cleanup() {
  if [[ -n "$app_pid" ]]; then kill "$app_pid" 2>/dev/null; fi
  rm -rf -- "$qa_root"
}
trap cleanup EXIT

for scenario in ${QA_SCENARIOS:-en-desktop en-mobile ar-desktop ar-mobile}; do
  export QA_LOCALE="${scenario%-*}" QA_VIEW="${scenario#*-}"
  export DB_DATABASE="cx_qa_${scenario/-/_}"
  PGPASSWORD="$QA_DB_PASSWORD" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" "$DB_DATABASE"

  export LARAVEL_STORAGE_PATH="$qa_root/$scenario"
  mkdir -p "$LARAVEL_STORAGE_PATH"/{app,logs,framework/{sessions,cache/data,views}}
  (
    cd public
    exec php -d variables_order=EGPCS -S "127.0.0.1:${QA_HTTP_PORT:-18081}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
  ) > "$qa_root/http.log" 2>&1 &
  app_pid=$!
  curl --noproxy '*' --silent --show-error --fail --retry 10 --retry-connrefused "$QA_BASE_URL/up" > /dev/null
  if ! node scripts/qa-installation.mjs; then
    tail -n 20 "$qa_root/http.log" >&2
    if [[ -s "$LARAVEL_STORAGE_PATH/logs/laravel.log" ]]; then
      grep -E 'Installation could not finish|FreeSWITCH ESL' "$LARAVEL_STORAGE_PATH/logs/laravel.log" >&2 || :
    fi
    exit 1
  fi
  PGPASSWORD="$QA_DB_PASSWORD" psql -X -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -At \
    -c "select count(*) from users join model_has_roles on users.id::text = model_has_roles.model_id join roles on roles.id = model_has_roles.role_id where roles.name = 'superadmin';" | grep -x 1
  if [[ ! -s "$LARAVEL_STORAGE_PATH/installation-settings" ]]; then
    echo "The encrypted installation settings are missing from the scenario storage directory." >&2
    exit 1
  fi

  # ── Reboot simulation (en-desktop only) ─────────────────────────────────────
  #
  # Stage 4 requires proof that after a server restart:
  #   • The database (PostgreSQL) retains all data (migrations, admin user)
  #   • The app starts cleanly (no in-memory-only state)
  #   • Previously created admin can log in again
  #   • The /setup route stays 404 (install flag is DB-backed)
  #
  # We simulate this by stopping the PHP dev server and restarting it,
  # then running a Playwright post-restart verification.
  if [[ "$scenario" == 'en-desktop' ]]; then
    echo ""
    echo "── Reboot simulation: stopping server ─────────────────────────────────"
    kill "$app_pid"
    status=0
    wait "$app_pid" 2>/dev/null || status=$?
    if [[ "$status" -ne 0 && "$status" -ne 143 ]]; then
      echo "Server exited with unexpected status $status before reboot simulation." >&2
      exit "$status"
    fi
    app_pid=''
    echo "── Reboot simulation: restarting server ───────────────────────────────"
    (
      cd public
      exec php -d variables_order=EGPCS -S "127.0.0.1:${QA_HTTP_PORT:-18081}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
    ) >> "$qa_root/http.log" 2>&1 &
    app_pid=$!
    curl --noproxy '*' --silent --show-error --fail --retry 15 --retry-connrefused "$QA_BASE_URL/up" > /dev/null
    echo "── Reboot simulation: server restarted — running post-restart checks ──"
    if ! node scripts/qa-post-restart.mjs; then
      tail -n 20 "$qa_root/http.log" >&2
      if [[ -s "$LARAVEL_STORAGE_PATH/logs/laravel.log" ]]; then
        tail -n 20 "$LARAVEL_STORAGE_PATH/logs/laravel.log" >&2
      fi
      echo "Post-restart check failed." >&2
      exit 1
    fi
    echo "── Reboot simulation: PASSED ───────────────────────────────────────────"
  fi

  kill "$app_pid"
  # A dev server may report either graceful shutdown or SIGTERM.
  status=0
  wait "$app_pid" 2>/dev/null || status=$?
  if [[ "$status" -ne 0 && "$status" -ne 143 ]]; then
    echo "The PHP development server exited with status $status after $scenario." >&2
    exit "$status"
  fi
  app_pid=''
done
