#!/usr/bin/env bash
# Debian / Ubuntu package installation adapter

set -euo pipefail

# ── APT helpers ───────────────────────────────────────────────────────────────

_apt() {
  DEBIAN_FRONTEND=noninteractive apt-get -y -q "$@"
}

nx_apt_update() {
  nx_spinner_start "Updating package lists…"
  _apt update 2>&1 | nx_log_passthrough || { nx_spinner_stop; nx_fatal "apt-get update failed"; }
  nx_spinner_stop
  nx_ok "Package lists updated"
}

# ── Add Sury PHP repository (Debian & Ubuntu) ──────────────────────────────────

nx_add_sury_php() {
  if [[ -f /etc/apt/sources.list.d/sury-php.list ]]; then
    nx_ok "Sury PHP repository already configured"
    return
  fi

  nx_info "Refreshing package lists…"
  _apt update -qq 2>&1 | nx_log_passthrough || true

  nx_info "Adding Sury PHP 8.3 repository…"
  _apt install -y ca-certificates apt-transport-https lsb-release gnupg curl

  local keyring='/usr/share/keyrings/sury-php.gpg'
  curl -sSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o "$keyring"
  chmod 644 "$keyring"

  local codename
  codename="$(. /etc/os-release; echo "${VERSION_CODENAME:-$(lsb_release -cs)}")"

  printf 'deb [signed-by=%s] https://packages.sury.org/php/ %s main\n' \
    "$keyring" "$codename" > /etc/apt/sources.list.d/sury-php.list

  _apt update 2>&1 | nx_log_passthrough
  nx_ok "Sury PHP repository added"
}

# ── Install PHP 8.3 + extensions ──────────────────────────────────────────────

nx_install_php_debian() {
  nx_step "Installing PHP 8.3 and required extensions"
  nx_add_sury_php

  local packages=(
    php8.3-cli php8.3-fpm
    php8.3-pgsql php8.3-redis
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip
    php8.3-bcmath php8.3-intl php8.3-gd php8.3-sockets
    php8.3-opcache
  )

  nx_spinner_start "Installing PHP packages…"
  _apt install -y "${packages[@]}" 2>&1 | nx_log_passthrough
  nx_spinner_stop
  nx_ok "PHP $(php8.3 -r 'echo PHP_VERSION;') installed"

  # Pin to php8.3 as the system default
  update-alternatives --set php /usr/bin/php8.3 2>/dev/null || true
}

# ── Install PostgreSQL 16 ─────────────────────────────────────────────────────

nx_install_postgresql_debian() {
  nx_step "Installing PostgreSQL 16"

  if ! [[ -f /etc/apt/sources.list.d/pgdg.list ]]; then
    local keyring='/usr/share/keyrings/postgresql-keyring.gpg'
    curl -sSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o "$keyring"
    chmod 644 "$keyring"

    local codename
    codename="$(. /etc/os-release; echo "${VERSION_CODENAME:-$(lsb_release -cs)}")"
    printf 'deb [signed-by=%s] https://apt.postgresql.org/pub/repos/apt %s-pgdg main\n' \
      "$keyring" "$codename" > /etc/apt/sources.list.d/pgdg.list

    _apt update 2>&1 | nx_log_passthrough
  fi

  nx_spinner_start "Installing PostgreSQL 16…"
  _apt install -y postgresql-16 2>&1 | nx_log_passthrough
  nx_spinner_stop

  systemctl enable postgresql --now
  nx_ok "PostgreSQL $(psql --version | awk '{print $3}') installed and started"
}

# ── Install Redis 7 ──────────────────────────────────────────────────────────

nx_install_redis_debian() {
  nx_step "Installing Redis 7"

  if dpkg -l redis-server &>/dev/null; then
    local ver; ver="$(redis-server --version 2>/dev/null | awk '{print $3}' | tr -d 'v=')"
    nx_ok "Redis $ver already installed"
    systemctl enable redis-server --now || true
    return
  fi

  nx_spinner_start "Installing Redis…"
  _apt install -y redis-server 2>&1 | nx_log_passthrough
  nx_spinner_stop

  # Harden: bind to localhost only, disable protected-mode (we set a password later)
  local redis_conf='/etc/redis/redis.conf'
  if [[ -f "$redis_conf" ]]; then
    sed -i 's/^bind .*/bind 127.0.0.1 -::1/' "$redis_conf"
  fi

  systemctl enable redis-server --now
  nx_ok "Redis $(redis-server --version | awk '{print $3}' | tr -d 'v=') installed and started"
}

# ── Install Nginx ─────────────────────────────────────────────────────────────

nx_install_nginx_debian() {
  nx_step "Installing Nginx"

  nx_spinner_start "Installing Nginx…"
  _apt install -y nginx 2>&1 | nx_log_passthrough
  nx_spinner_stop

  systemctl enable nginx
  nx_ok "Nginx $(nginx -v 2>&1 | awk -F'/' '{print $2}') installed"
}

# ── Install Certbot ───────────────────────────────────────────────────────────

nx_install_certbot_debian() {
  if [[ "${NX_HTTPS:-}" != 'certbot' ]]; then return; fi
  nx_step "Installing Certbot for Let's Encrypt"
  _apt install -y certbot python3-certbot-nginx 2>&1 | nx_log_passthrough
  nx_ok "Certbot installed"
}

# ── Install Node.js 22 (for asset build) ─────────────────────────────────────

nx_install_node_debian() {
  if command -v node &>/dev/null; then
    local nver; nver="$(node --version | tr -d 'v')"
    local nmaj="${nver%%.*}"
    if [[ "$nmaj" -ge 20 ]]; then
      nx_ok "Node.js $nver already installed"
      return
    fi
  fi

  nx_step "Installing Node.js 22 LTS"
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash - 2>&1 | nx_log_passthrough
  _apt install -y nodejs 2>&1 | nx_log_passthrough
  nx_ok "Node.js $(node --version) installed"
}

# ── Install Composer ──────────────────────────────────────────────────────────

nx_install_composer_debian() {
  if command -v composer &>/dev/null; then
    nx_ok "Composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}') already installed"
    return
  fi

  nx_step "Installing Composer"
  local tmp; tmp="$(mktemp)"
  curl -sSL https://getcomposer.org/installer -o "$tmp"
  EXPECTED_CHECKSUM="$(curl -sSL https://composer.github.io/installer.sig)"
  ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384','$tmp');")"
  if [[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
    rm -f "$tmp"
    nx_fatal "Composer installer checksum verification failed"
  fi
  php "$tmp" --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f "$tmp"
  nx_ok "Composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}') installed"
}

# ── Log passthrough (suppress verbose package output except on error) ─────────

nx_log_passthrough() {
  if [[ "${NX_VERBOSE:-}" == '1' ]]; then
    cat
  else
    cat > /dev/null
  fi
}
