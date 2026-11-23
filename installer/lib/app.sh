#!/usr/bin/env bash
# Laravel application deployment module

set -euo pipefail

readonly NX_APP_USER='nexvary'
readonly NX_APP_DIR='/var/www/nexvary-cx'
readonly NX_REPO_URL='https://github.com/nexvary/NEXVARY-CX-Cloud.git'

# File written by the installer after every successful deploy to record
# the exact commit SHA that is running on this host.
readonly NX_INSTALLED_REVISION_FILE='/etc/nexvary/installed_revision'

# ── Create application system user ───────────────────────────────────────────

nx_create_app_user() {
  if id "$NX_APP_USER" &>/dev/null; then
    nx_ok "System user '$NX_APP_USER' already exists"
    return
  fi

  nx_step "Creating application system user"
  useradd --system --home-dir "$NX_APP_DIR" --create-home \
    --shell /sbin/nologin --comment 'NEXVARY CX Cloud' "$NX_APP_USER"
  nx_ok "User '$NX_APP_USER' created"
}

# ── Resolve the target commit SHA ─────────────────────────────────────────────
#
# Priority (highest to lowest):
#   1. NX_RELEASE_COMMIT  — set by the installer from the bundle's RELEASE file;
#                           this is the ONLY authoritative source when installing
#                           from a versioned bundle.
#   2. NX_INSTALL_REF     — a branch/tag passed via --ref= (unbundled installs).
#
# A branch name is accepted for --ref but only NX_RELEASE_COMMIT guarantees
# that the installed source matches the tested bundle.

_nx_resolve_target_sha() {
  local commit_sha=''

  if [[ -n "${NX_RELEASE_COMMIT:-}" ]]; then
    # Validate it looks like a full 40-hex SHA
    if [[ ! "${NX_RELEASE_COMMIT}" =~ ^[0-9a-f]{40}$ ]]; then
      nx_fatal "NX_RELEASE_COMMIT '${NX_RELEASE_COMMIT}' is not a valid 40-character hex SHA"
    fi
    commit_sha="${NX_RELEASE_COMMIT}"
    nx_info "Bundle release commit: ${commit_sha}"
    printf '%s' "${commit_sha}"
    return
  fi

  if [[ -n "${NX_INSTALL_REF:-}" ]]; then
    nx_warn "NX_INSTALL_REF='${NX_INSTALL_REF}' used (branch/tag, not a pinned SHA)."
    nx_warn "Install from a versioned bundle to guarantee reproducibility."
    printf '%s' "${NX_INSTALL_REF}"
    return
  fi

  nx_fatal "No target revision specified. Install from a versioned bundle or pass --ref=."
}

# ── Version-mismatch guard ────────────────────────────────────────────────────
#
# On an existing installation, compare the previously installed SHA against the
# target.  Refuse to proceed unless:
#   a) Target and installed are identical (already up-to-date — no-op).
#   b) The caller explicitly passes --upgrade-from=<installed-sha>, signalling
#      that they understand the transition and accept it.
#
# This prevents silent silent rollbacks or cross-version migrations.

nx_check_version_mismatch() {
  local target_ref="$1"

  [[ ! -f "${NX_INSTALLED_REVISION_FILE}" ]] && return   # fresh install

  local installed_sha
  installed_sha="$(cat "${NX_INSTALLED_REVISION_FILE}" | tr -d '[:space:]')"

  if [[ -z "$installed_sha" ]]; then
    nx_warn "Installed revision file is empty — treating as fresh install"
    return
  fi

  # If we have a pinned target SHA, do an exact SHA comparison.
  # If the target is a branch name, skip the guard (branch-based installs
  # never guarantee reproducibility anyway).
  if [[ ! "${target_ref}" =~ ^[0-9a-f]{40}$ ]]; then
    nx_warn "Target ref '${target_ref}' is a branch name — version-mismatch guard skipped"
    return
  fi

  if [[ "$installed_sha" == "$target_ref" ]]; then
    nx_ok "Already at target revision ${target_ref:0:12} — skipping source deploy"
    return 0
  fi

  # Mismatch: check whether the caller acknowledged this upgrade explicitly.
  local upgrade_from="${NX_UPGRADE_FROM:-}"
  if [[ -n "$upgrade_from" ]]; then
    if [[ "$upgrade_from" == "$installed_sha" ]]; then
      nx_info "Explicit upgrade: ${installed_sha:0:12} → ${target_ref:0:12}"
      return
    else
      nx_fatal "Version mismatch: installed=${installed_sha:0:12} but --upgrade-from=${upgrade_from:0:12} (expected them to match)"
    fi
  fi

  # No explicit upgrade flag — refuse.
  nx_fatal "$(cat <<MSG
Version mismatch detected.

  Installed : ${installed_sha}
  Target    : ${target_ref}

To upgrade, re-run the installer with:
  --upgrade-from=${installed_sha}

This flag confirms you have read the upgrade notes and accept the transition.
Only consecutive, documented upgrade paths are supported.
MSG
)"
}

# ── Deploy application code ───────────────────────────────────────────────────

nx_deploy_app() {
  nx_step "Deploying NEXVARY CX Cloud application"

  local target_ref
  target_ref="$(_nx_resolve_target_sha)"

  nx_check_version_mismatch "$target_ref"

  if [[ -d "${NX_APP_DIR}/.git" ]]; then
    _nx_update_app "$target_ref"
  else
    _nx_clone_app "$target_ref"
  fi

  # Record the exact SHA that is now installed.
  _nx_record_installed_sha

  nx_ok "Application code deployed to $NX_APP_DIR"
}

# ── Clone (fresh install) ─────────────────────────────────────────────────────

_nx_clone_app() {
  local target_ref="$1"
  nx_info "Cloning from $NX_REPO_URL …"
  nx_spinner_start "Downloading NEXVARY CX Cloud…"
  mkdir -p "$NX_APP_DIR"

  if [[ "${target_ref}" =~ ^[0-9a-f]{40}$ ]]; then
    # Pin to exact commit: clone default branch (depth=1 to avoid full history),
    # then fetch the exact object and checkout.
    git clone --no-checkout --depth=1 "$NX_REPO_URL" "$NX_APP_DIR" --quiet \
      2>&1 | nx_log_passthrough
    git -C "$NX_APP_DIR" fetch --depth=1 origin "${target_ref}" --quiet \
      2>&1 | nx_log_passthrough
    git -C "$NX_APP_DIR" checkout FETCH_HEAD --quiet 2>&1 | nx_log_passthrough
  else
    # Branch/tag ref — depth clone by name.
    git clone --depth=1 --branch "${target_ref}" "$NX_REPO_URL" "$NX_APP_DIR" --quiet \
      2>&1 | nx_log_passthrough
  fi

  chown -R "${NX_APP_USER}:${NX_APP_USER}" "$NX_APP_DIR"
  nx_spinner_stop
}

# ── Update (existing install) ─────────────────────────────────────────────────

_nx_update_app() {
  local target_ref="$1"
  nx_info "Updating installation to ${target_ref:0:12}…"
  nx_spinner_start "Fetching code…"

  if [[ "${target_ref}" =~ ^[0-9a-f]{40}$ ]]; then
    # Fetch only the target object — avoids pulling unrelated history.
    sudo -u "$NX_APP_USER" git -C "$NX_APP_DIR" fetch --depth=1 origin "${target_ref}" --quiet \
      2>&1 | nx_log_passthrough
    sudo -u "$NX_APP_USER" git -C "$NX_APP_DIR" checkout FETCH_HEAD --detach --quiet \
      2>&1 | nx_log_passthrough
  else
    sudo -u "$NX_APP_USER" git -C "$NX_APP_DIR" fetch origin --quiet 2>&1 | nx_log_passthrough
    sudo -u "$NX_APP_USER" git -C "$NX_APP_DIR" checkout "${target_ref}" --quiet 2>&1 | nx_log_passthrough
    sudo -u "$NX_APP_USER" git -C "$NX_APP_DIR" reset --hard "origin/${target_ref}" --quiet \
      2>&1 | nx_log_passthrough
  fi

  nx_spinner_stop
}

# ── Record installed revision ─────────────────────────────────────────────────

_nx_record_installed_sha() {
  local actual_sha
  actual_sha="$(git -C "$NX_APP_DIR" rev-parse HEAD 2>/dev/null || echo '')"

  if [[ -z "$actual_sha" ]]; then
    nx_warn "Could not determine installed SHA — /etc/nexvary/installed_revision not written"
    return
  fi

  # Verify the installed SHA matches the target when a pinned commit was requested.
  if [[ -n "${NX_RELEASE_COMMIT:-}" && "$actual_sha" != "${NX_RELEASE_COMMIT}" ]]; then
    nx_fatal "Installed SHA ${actual_sha} does not match expected ${NX_RELEASE_COMMIT} — aborting"
  fi

  mkdir -p /etc/nexvary
  printf '%s\n' "$actual_sha" > "${NX_INSTALLED_REVISION_FILE}"
  chmod 644 "${NX_INSTALLED_REVISION_FILE}"
  nx_ok "Installed revision recorded: ${actual_sha}"
}

# ── Install PHP dependencies ──────────────────────────────────────────────────

nx_composer_install() {
  nx_step "Installing PHP dependencies (Composer)"

  nx_spinner_start "Running composer install…"
  sudo -u "$NX_APP_USER" composer install \
    --no-interaction --prefer-dist --optimize-autoloader --no-dev \
    --working-dir="$NX_APP_DIR" --quiet 2>&1 | nx_log_passthrough
  nx_spinner_stop
  nx_ok "PHP dependencies installed"
}

# ── Build frontend assets ─────────────────────────────────────────────────────

nx_build_frontend() {
  nx_step "Building frontend assets (Vite)"

  nx_spinner_start "Running npm ci…"
  sudo -u "$NX_APP_USER" bash -c "cd '$NX_APP_DIR' && npm ci --quiet" 2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_spinner_start "Running npm run build…"
  sudo -u "$NX_APP_USER" bash -c "cd '$NX_APP_DIR' && npm run build" 2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_ok "Frontend assets built"
}

# ── Generate application environment ─────────────────────────────────────────

nx_generate_env() {
  nx_step "Generating application environment"

  local env_file="${NX_APP_DIR}/.env"

  if [[ -f "$env_file" ]]; then
    nx_info ".env already exists — preserving existing configuration"
    return
  fi

  # Read FreeSWITCH credentials if present
  local fs_host='127.0.0.1' fs_port='8021' fs_password=''
  if [[ -f /etc/nexvary/freeswitch.conf ]]; then
    # shellcheck source=/dev/null
    source /etc/nexvary/freeswitch.conf 2>/dev/null || true
    fs_host="${FREESWITCH_HOST:-127.0.0.1}"
    fs_port="${FREESWITCH_ESL_PORT:-8021}"
    fs_password="${FREESWITCH_ESL_PASSWORD:-}"
  fi

  local app_key; app_key="base64:$(openssl rand -base64 32)"
  local setup_token; setup_token="$(openssl rand -hex 32)"

  cat > "$env_file" <<EOF
APP_NAME="NEXVARY CX Cloud"
APP_ENV=production
APP_KEY=${app_key}
APP_DEBUG=false
APP_URL=http://${NX_DOMAIN:-localhost}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nexvary_cx
DB_USERNAME=nexvary_cx
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis

FREESWITCH_HOST=${fs_host}
FREESWITCH_ESL_PORT=${fs_port}
FREESWITCH_ESL_PASSWORD=${fs_password}

NEXVARY_INSTALL_SECRET=${setup_token}

MAIL_MAILER=log
EOF
  chmod 600 "$env_file"
  chown "${NX_APP_USER}:${NX_APP_USER}" "$env_file"

  # Save setup token for display at end of installation
  printf '%s' "$setup_token" > /etc/nexvary/setup_token
  chmod 640 /etc/nexvary/setup_token

  nx_ok ".env generated (APP_KEY set, setup token saved to /etc/nexvary/setup_token)"
  nx_warn "Database and Redis passwords must be set before the browser wizard runs."
}

# ── Create PostgreSQL database and user ───────────────────────────────────────

nx_configure_postgresql() {
  nx_step "Configuring PostgreSQL database"

  local db_user='nexvary_cx'
  local db_name='nexvary_cx'

  # Generate a strong password
  local db_pass; db_pass="$(openssl rand -base64 32 | tr -d '/+=\n' | head -c 40)"

  # Create user and database if they don't exist
  if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${db_user}'" | grep -q 1; then
    sudo -u postgres psql -c "CREATE USER ${db_user} WITH PASSWORD '${db_pass}';"
    sudo -u postgres psql -c "CREATE DATABASE ${db_name} OWNER ${db_user} ENCODING 'UTF8' LC_COLLATE 'C.UTF-8' LC_CTYPE 'C.UTF-8' TEMPLATE template0;"
    nx_ok "PostgreSQL user '$db_user' and database '$db_name' created"
  else
    # Reset password if already exists
    db_pass="$(sudo -u postgres psql -tAc "SELECT passwd FROM pg_shadow WHERE usename='${db_user}'" 2>/dev/null || echo '')"
    if [[ -z "$db_pass" ]]; then
      db_pass="$(openssl rand -base64 32 | tr -d '/+=\n' | head -c 40)"
      sudo -u postgres psql -c "ALTER USER ${db_user} WITH PASSWORD '${db_pass}';" 2>/dev/null || true
    fi
    nx_ok "PostgreSQL database '$db_name' already exists"
  fi

  # Update .env with database password
  local env_file="${NX_APP_DIR}/.env"
  if [[ -f "$env_file" ]]; then
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${db_pass}|" "$env_file"
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${db_user}|" "$env_file"
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${db_name}|" "$env_file"
  fi

  # Save credentials
  local creds_file='/etc/nexvary/database.conf'
  printf 'DB_HOST=127.0.0.1\nDB_PORT=5432\nDB_DATABASE=%s\nDB_USERNAME=%s\nDB_PASSWORD=%s\n' \
    "$db_name" "$db_user" "$db_pass" > "$creds_file"
  chmod 640 "$creds_file"
  chown "root:${NX_APP_USER}" "$creds_file" 2>/dev/null || true
}

# ── Configure Redis password ───────────────────────────────────────────────────

nx_configure_redis() {
  nx_step "Configuring Redis password"

  local redis_pass; redis_pass="$(openssl rand -base64 32 | tr -d '/+=\n' | head -c 40)"
  local redis_conf='/etc/redis/redis.conf'
  if [[ ! -f "$redis_conf" ]]; then redis_conf='/etc/redis.conf'; fi

  if [[ -f "$redis_conf" ]]; then
    if grep -q '^requirepass ' "$redis_conf"; then
      redis_pass="$(grep '^requirepass ' "$redis_conf" | awk '{print $2}')"
      nx_info "Redis already has a password configured"
    else
      echo "requirepass $redis_pass" >> "$redis_conf"
      systemctl restart redis-server 2>/dev/null || systemctl restart redis 2>/dev/null || true
    fi
  fi

  # Update .env
  local env_file="${NX_APP_DIR}/.env"
  if [[ -f "$env_file" ]]; then
    sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${redis_pass}|" "$env_file"
  fi

  # Save credentials
  printf 'REDIS_HOST=127.0.0.1\nREDIS_PORT=6379\nREDIS_PASSWORD=%s\n' \
    "$redis_pass" > /etc/nexvary/redis.conf
  chmod 640 /etc/nexvary/redis.conf

  nx_ok "Redis password configured"
}

# ── Storage permissions ───────────────────────────────────────────────────────

nx_set_permissions() {
  nx_step "Setting file permissions"

  # Storage and cache must be writable by the app user
  local writable_dirs=(
    "${NX_APP_DIR}/storage"
    "${NX_APP_DIR}/bootstrap/cache"
  )

  for dir in "${writable_dirs[@]}"; do
    mkdir -p "$dir"
    chown -R "${NX_APP_USER}:${NX_APP_USER}" "$dir"
    chmod -R 755 "$dir"
  done

  # Framework subdirectories
  mkdir -p \
    "${NX_APP_DIR}/storage/app/public" \
    "${NX_APP_DIR}/storage/framework/sessions" \
    "${NX_APP_DIR}/storage/framework/views" \
    "${NX_APP_DIR}/storage/framework/cache/data" \
    "${NX_APP_DIR}/storage/logs"

  chown -R "${NX_APP_USER}:${NX_APP_USER}" "${NX_APP_DIR}/storage"

  # Application files — owned by root, readable by app user
  chown -R "root:${NX_APP_USER}" "${NX_APP_DIR}"
  chmod -R o-rwx "${NX_APP_DIR}"

  # .env must be root:app_user 640
  if [[ -f "${NX_APP_DIR}/.env" ]]; then
    chown "root:${NX_APP_USER}" "${NX_APP_DIR}/.env"
    chmod 640 "${NX_APP_DIR}/.env"
  fi

  nx_ok "Permissions set"
}

# ── Run migrations ────────────────────────────────────────────────────────────

nx_run_migrations() {
  nx_step "Running database migrations"

  nx_spinner_start "Running migrations…"
  sudo -u "$NX_APP_USER" php "${NX_APP_DIR}/artisan" migrate --force --no-interaction \
    2>&1 | nx_log_passthrough
  nx_spinner_stop
  nx_ok "Database migrations completed"
}

# ── Artisan cache ─────────────────────────────────────────────────────────────

nx_cache_app() {
  nx_step "Caching configuration and routes"

  sudo -u "$NX_APP_USER" php "${NX_APP_DIR}/artisan" config:cache --no-interaction 2>&1 | nx_log_passthrough
  sudo -u "$NX_APP_USER" php "${NX_APP_DIR}/artisan" route:cache --no-interaction  2>&1 | nx_log_passthrough
  sudo -u "$NX_APP_USER" php "${NX_APP_DIR}/artisan" view:cache --no-interaction   2>&1 | nx_log_passthrough
  nx_ok "Application caches warmed"
}
