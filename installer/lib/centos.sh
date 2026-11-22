#!/usr/bin/env bash
# CentOS Stream 9/10 package installation adapter

set -euo pipefail

# ── DNF helpers ───────────────────────────────────────────────────────────────

_dnf() {
  dnf -y -q "$@"
}

nx_dnf_update() {
  nx_spinner_start "Updating package lists…"
  _dnf makecache 2>&1 | nx_log_passthrough
  nx_spinner_stop
  nx_ok "Package cache updated"
}

# ── Enable EPEL and Remi repositories ────────────────────────────────────────

nx_enable_epel_centos() {
  nx_step "Enabling EPEL repository"
  _dnf install -y epel-release 2>&1 | nx_log_passthrough
  _dnf install -y 'dnf-command(config-manager)' 2>&1 | nx_log_passthrough
  nx_ok "EPEL enabled"
}

nx_enable_remi_centos() {
  nx_step "Enabling Remi PHP repository"
  local stream="${NX_OS_VERSION:-9}"
  local remi_url="https://rpms.remirepo.net/enterprise/remi-release-${stream}.rpm"
  _dnf install -y "$remi_url" 2>&1 | nx_log_passthrough
  _dnf config-manager --set-enabled "remi-safe" 2>&1 | nx_log_passthrough
  nx_ok "Remi repository enabled"
}

# ── Install PHP 8.3 ───────────────────────────────────────────────────────────

nx_install_php_centos() {
  nx_step "Installing PHP 8.3 via Remi"
  nx_enable_epel_centos
  nx_enable_remi_centos

  _dnf module reset php -y 2>&1 | nx_log_passthrough || true
  _dnf module enable php:remi-8.3 -y 2>&1 | nx_log_passthrough

  local packages=(
    php php-cli php-fpm
    php-pgsql php-pecl-redis6
    php-mbstring php-xml php-curl php-zip
    php-bcmath php-intl php-gd php-sockets
    php-opcache
  )

  nx_spinner_start "Installing PHP packages…"
  _dnf install -y "${packages[@]}" 2>&1 | nx_log_passthrough
  nx_spinner_stop
  nx_ok "PHP $(php -r 'echo PHP_VERSION;') installed"
}

# ── Install PostgreSQL 16 ─────────────────────────────────────────────────────

nx_install_postgresql_centos() {
  nx_step "Installing PostgreSQL 16"

  local stream="${NX_OS_VERSION:-9}"

  if ! rpm -q postgresql16-server &>/dev/null; then
    _dnf install -y "https://download.postgresql.org/pub/repos/yum/reporpms/EL-${stream}-x86_64/pgdg-redhat-repo-latest.noarch.rpm" \
      2>&1 | nx_log_passthrough
    _dnf module disable postgresql -y 2>&1 | nx_log_passthrough || true

    nx_spinner_start "Installing PostgreSQL 16…"
    _dnf install -y postgresql16 postgresql16-server 2>&1 | nx_log_passthrough
    nx_spinner_stop

    /usr/pgsql-16/bin/postgresql-16-setup initdb
    sed -i "s|^#listen_addresses.*|listen_addresses = 'localhost'|" \
      /var/lib/pgsql/16/data/postgresql.conf
  fi

  systemctl enable postgresql-16 --now
  nx_ok "PostgreSQL $(psql --version 2>/dev/null | awk '{print $3}') installed and started"
}

# ── Install Redis 7 ───────────────────────────────────────────────────────────

nx_install_redis_centos() {
  nx_step "Installing Redis 7"

  if rpm -q redis &>/dev/null; then
    nx_ok "Redis already installed"
    systemctl enable redis --now || true
    return
  fi

  nx_spinner_start "Installing Redis…"
  _dnf install -y redis 2>&1 | nx_log_passthrough
  nx_spinner_stop

  local redis_conf='/etc/redis/redis.conf'
  if [[ ! -f "$redis_conf" ]]; then redis_conf='/etc/redis.conf'; fi
  if [[ -f "$redis_conf" ]]; then
    sed -i 's/^bind .*/bind 127.0.0.1 -::1/' "$redis_conf" || true
  fi

  systemctl enable redis --now
  nx_ok "Redis installed and started"
}

# ── Install Nginx ─────────────────────────────────────────────────────────────

nx_install_nginx_centos() {
  nx_step "Installing Nginx"

  nx_spinner_start "Installing Nginx…"
  _dnf install -y nginx 2>&1 | nx_log_passthrough
  nx_spinner_stop

  systemctl enable nginx
  nx_ok "Nginx $(nginx -v 2>&1 | awk -F'/' '{print $2}') installed"
}

# ── Install Certbot ───────────────────────────────────────────────────────────

nx_install_certbot_centos() {
  if [[ "${NX_HTTPS:-}" != 'certbot' ]]; then return; fi
  nx_step "Installing Certbot for Let's Encrypt"
  _dnf install -y certbot python3-certbot-nginx 2>&1 | nx_log_passthrough
  nx_ok "Certbot installed"
}

# ── Install Node.js 22 ────────────────────────────────────────────────────────

nx_install_node_centos() {
  if command -v node &>/dev/null; then
    local nver; nver="$(node --version | tr -d 'v')"
    local nmaj="${nver%%.*}"
    if [[ "$nmaj" -ge 20 ]]; then
      nx_ok "Node.js $nver already installed"
      return
    fi
  fi

  nx_step "Installing Node.js 22 LTS"
  curl -fsSL https://rpm.nodesource.com/setup_22.x | bash - 2>&1 | nx_log_passthrough
  _dnf install -y nodejs 2>&1 | nx_log_passthrough
  nx_ok "Node.js $(node --version) installed"
}

# ── Install Composer ──────────────────────────────────────────────────────────

nx_install_composer_centos() {
  if command -v composer &>/dev/null; then
    nx_ok "Composer already installed"
    return
  fi

  nx_step "Installing Composer"
  _dnf install -y curl php-cli 2>&1 | nx_log_passthrough
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
  nx_ok "Composer installed"
}

# ── SELinux policy setup ──────────────────────────────────────────────────────

nx_configure_selinux_centos() {
  if ! command -v getenforce &>/dev/null; then return; fi
  local mode; mode="$(getenforce 2>/dev/null || echo Disabled)"
  if [[ "$mode" == 'Disabled' ]]; then return; fi

  nx_step "Configuring SELinux policies (mode: $mode)"
  nx_info "NEXVARY CX Cloud requires specific SELinux file contexts."
  nx_info "SELinux will NOT be disabled — only required contexts are set."

  _dnf install -y policycoreutils-python-utils 2>&1 | nx_log_passthrough || true

  # Allow Nginx to connect to PHP-FPM and to the app
  setsebool -P httpd_can_network_connect 1 2>/dev/null || true
  setsebool -P httpd_can_network_relay 1 2>/dev/null || true
  setsebool -P httpd_read_user_content 1 2>/dev/null || true

  nx_ok "SELinux booleans set"
}

# ── Firewall setup ────────────────────────────────────────────────────────────

nx_configure_firewall_centos() {
  if ! command -v firewall-cmd &>/dev/null; then return; fi
  if ! systemctl is-active --quiet firewalld; then return; fi

  nx_step "Opening firewall ports (HTTP/HTTPS)"
  nx_info "firewalld is active. Opening ports 80 and 443 — not disabling the firewall."
  firewall-cmd --permanent --add-service=http  2>/dev/null || true
  firewall-cmd --permanent --add-service=https 2>/dev/null || true
  firewall-cmd --reload 2>/dev/null || true
  nx_ok "Firewall ports 80/443 opened"
}

# ── Log passthrough ───────────────────────────────────────────────────────────

nx_log_passthrough() {
  if [[ "${NX_VERBOSE:-}" == '1' ]]; then
    cat
  else
    cat > /dev/null
  fi
}
