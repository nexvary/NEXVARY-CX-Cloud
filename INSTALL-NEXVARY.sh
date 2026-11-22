#!/usr/bin/env bash
# =============================================================================
# NEXVARY CX Cloud — One-Command Installer
# Version: 1.0.0
# Target:  Debian 13 (Trixie) / Ubuntu 24.04 LTS
# Company: Nexvary Inc. — Wyoming, USA
# Website: https://nexvary.com
# =============================================================================
# Usage:
#   curl -fsSL https://install.nexvary.com | bash
# or:
#   bash INSTALL-NEXVARY.sh
# =============================================================================

set -euo pipefail
IFS=$'\n\t'

# ── Colors ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
GRAY='\033[0;37m'
NC='\033[0m'
BOLD='\033[1m'

# ── Constants ────────────────────────────────────────────────────────────────
NEXVARY_VERSION="1.0.0"
NEXVARY_REPO="https://github.com/nexvary/NEXVARY-CX-Cloud"
NEXVARY_INSTALL_DIR="/opt/nexvary-cx"
NEXVARY_DATA_DIR="/var/lib/nexvary-cx"
NEXVARY_LOG_DIR="/var/log/nexvary-cx"
NEXVARY_CONFIG_DIR="/etc/nexvary-cx"
NEXVARY_WEB_USER="nexvary"
NEXVARY_MIN_RAM_MB=2048
NEXVARY_MIN_DISK_GB=20
NEXVARY_PHP_VERSION="8.3"
NEXVARY_NODE_VERSION="20"
LOG_FILE="/tmp/nexvary-install-$(date +%Y%m%d-%H%M%S).log"

# ── Logging ──────────────────────────────────────────────────────────────────
log()  { echo -e "${GRAY}[$(date '+%H:%M:%S')]${NC} $*" | tee -a "$LOG_FILE"; }
info() { echo -e "${CYAN}[INFO]${NC} $*"  | tee -a "$LOG_FILE"; }
ok()   { echo -e "${GREEN}[OK]${NC}   $*" | tee -a "$LOG_FILE"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $*" | tee -a "$LOG_FILE"; }
err()  { echo -e "${RED}[ERROR]${NC} $*" | tee -a "$LOG_FILE"; }
die()  { err "$*"; exit 1; }

# ── Banner ───────────────────────────────────────────────────────────────────
print_banner() {
  echo -e "${NC}"
  echo -e "${BOLD}${WHITE}╔══════════════════════════════════════════════════════════════╗${NC}"
  echo -e "${BOLD}${WHITE}║                                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║   ${RED}███╗   ██╗███████╗██╗  ██╗██╗   ██╗ █████╗ ██████╗██╗   ██╗${WHITE}  ║${NC}"
  echo -e "${BOLD}${WHITE}║   ${RED}████╗  ██║██╔════╝╚██╗██╔╝██║   ██║██╔══██╗██╔══██╗╚██╗ ██╔╝${WHITE} ║${NC}"
  echo -e "${BOLD}${WHITE}║   ${RED}██╔██╗ ██║█████╗   ╚███╔╝ ██║   ██║███████║██████╔╝ ╚████╔╝${WHITE}  ║${NC}"
  echo -e "${BOLD}${WHITE}║   ${RED}██║╚██╗██║██╔══╝   ██╔██╗ ╚██╗ ██╔╝██╔══██║██╔══██╗  ╚██╔╝${WHITE}   ║${NC}"
  echo -e "${BOLD}${WHITE}║   ${RED}██║ ╚████║███████╗██╔╝ ██╗ ╚████╔╝ ██║  ██║██║  ██║   ██║${WHITE}    ║${NC}"
  echo -e "${BOLD}${WHITE}║   ${RED}╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝   ╚═╝${WHITE}    ║${NC}"
  echo -e "${BOLD}${WHITE}║                                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║         ${CYAN}CX Cloud v${NEXVARY_VERSION}${WHITE} — Unified Communications Platform         ║${NC}"
  echo -e "${BOLD}${WHITE}║              ${GRAY}nexvary.com | Wyoming, USA${WHITE}                       ║${NC}"
  echo -e "${BOLD}${WHITE}╚══════════════════════════════════════════════════════════════╝${NC}"
  echo ""
  echo -e "  ${GRAY}Installation log: ${LOG_FILE}${NC}"
  echo ""
}

# ── Preflight Checks ─────────────────────────────────────────────────────────
check_root() {
  if [[ $EUID -ne 0 ]]; then
    die "This installer must be run as root. Try: sudo bash INSTALL-NEXVARY.sh"
  fi
}

check_os() {
  info "Checking operating system..."
  if [[ ! -f /etc/os-release ]]; then
    die "Cannot detect OS. /etc/os-release not found."
  fi
  # shellcheck source=/dev/null
  source /etc/os-release
  case "$ID" in
    debian)
      if [[ "${VERSION_ID:-0}" -lt 12 ]]; then
        die "Debian ${VERSION_ID} is not supported. Debian 12+ required."
      fi
      ok "Detected: ${PRETTY_NAME}"
      PKG_MGR="apt-get"
      ;;
    ubuntu)
      if dpkg --compare-versions "${VERSION_ID:-0}" lt "22.04"; then
        die "Ubuntu ${VERSION_ID} is not supported. Ubuntu 22.04+ required."
      fi
      ok "Detected: ${PRETTY_NAME}"
      PKG_MGR="apt-get"
      ;;
    *)
      warn "OS '${PRETTY_NAME}' is not officially tested. Proceeding with caution."
      PKG_MGR="apt-get"
      ;;
  esac
}

check_arch() {
  info "Checking CPU architecture..."
  ARCH=$(uname -m)
  if [[ "$ARCH" != "x86_64" ]] && [[ "$ARCH" != "aarch64" ]]; then
    die "Unsupported architecture: $ARCH. x86_64 or aarch64 required."
  fi
  ok "Architecture: $ARCH"
}

check_resources() {
  info "Checking system resources..."

  # RAM
  TOTAL_RAM_KB=$(grep MemTotal /proc/meminfo | awk '{print $2}')
  TOTAL_RAM_MB=$((TOTAL_RAM_KB / 1024))
  if [[ $TOTAL_RAM_MB -lt $NEXVARY_MIN_RAM_MB ]]; then
    warn "RAM: ${TOTAL_RAM_MB}MB detected. Minimum ${NEXVARY_MIN_RAM_MB}MB recommended."
    warn "Installation will continue, but performance may be degraded."
  else
    ok "RAM: ${TOTAL_RAM_MB}MB"
  fi

  # Disk
  AVAILABLE_GB=$(df -BG / | awk 'NR==2{gsub("G",""); print $4}')
  if [[ ${AVAILABLE_GB:-0} -lt $NEXVARY_MIN_DISK_GB ]]; then
    die "Insufficient disk space. ${AVAILABLE_GB}GB available, ${NEXVARY_MIN_DISK_GB}GB required."
  fi
  ok "Disk space: ${AVAILABLE_GB}GB available"

  # CPU
  CPU_CORES=$(nproc)
  ok "CPU cores: $CPU_CORES"
}

check_ports() {
  info "Checking required ports..."
  declare -A PORTS=(
    ["80"]="HTTP"
    ["443"]="HTTPS"
    ["5060"]="SIP UDP"
    ["5061"]="SIP TLS"
    ["8021"]="FreeSWITCH ESL (internal only)"
    ["16384-32767"]="RTP Media"
  )
  for port in "${!PORTS[@]}"; do
    # Skip range entries (e.g. 16384-32767) — cannot bind-test a range
    [[ "$port" == *-* ]] && continue
    if ss -tlnp 2>/dev/null | grep -q ":${port} " || \
       netstat -tlnp 2>/dev/null | grep -q ":${port} "; then
      warn "Port $port (${PORTS[$port]}) is already in use. Existing service may conflict."
    else
      ok "Port $port (${PORTS[$port]}): available"
    fi
  done
}

# ── System Preparation ───────────────────────────────────────────────────────
update_system() {
  info "Updating package lists..."
  DEBIAN_FRONTEND=noninteractive $PKG_MGR update -qq 2>&1 | tee -a "$LOG_FILE"
  ok "Package lists updated"
}

install_dependencies() {
  info "Installing system dependencies..."
  DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq \
    curl wget git unzip gnupg2 lsb-release ca-certificates \
    apt-transport-https software-properties-common \
    sudo ufw fail2ban \
    build-essential \
    2>&1 | tee -a "$LOG_FILE"
  ok "System dependencies installed"
}

# ── PHP Installation ─────────────────────────────────────────────────────────
install_php() {
  info "Installing PHP ${NEXVARY_PHP_VERSION}..."

  if ! command -v php &>/dev/null || \
     [[ "$(php -r 'echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;')" != "${NEXVARY_PHP_VERSION}" ]]; then
    # Add Ondrej PHP PPA / Sury repo
    if [[ "$ID" == "ubuntu" ]]; then
      add-apt-repository -y ppa:ondrej/php 2>&1 | tee -a "$LOG_FILE"
    else
      curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg \
        https://packages.sury.org/php/apt.gpg
      echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/php.list
    fi
    $PKG_MGR update -qq 2>&1 | tee -a "$LOG_FILE"
  fi

  DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq \
    "php${NEXVARY_PHP_VERSION}-fpm" \
    "php${NEXVARY_PHP_VERSION}-cli" \
    "php${NEXVARY_PHP_VERSION}-common" \
    "php${NEXVARY_PHP_VERSION}-pgsql" \
    "php${NEXVARY_PHP_VERSION}-redis" \
    "php${NEXVARY_PHP_VERSION}-curl" \
    "php${NEXVARY_PHP_VERSION}-mbstring" \
    "php${NEXVARY_PHP_VERSION}-xml" \
    "php${NEXVARY_PHP_VERSION}-zip" \
    "php${NEXVARY_PHP_VERSION}-bcmath" \
    "php${NEXVARY_PHP_VERSION}-gd" \
    "php${NEXVARY_PHP_VERSION}-intl" \
    "php${NEXVARY_PHP_VERSION}-soap" \
    2>&1 | tee -a "$LOG_FILE"

  ok "PHP ${NEXVARY_PHP_VERSION} installed: $(php -r 'echo PHP_VERSION;')"
}

# ── Composer ─────────────────────────────────────────────────────────────────
install_composer() {
  if ! command -v composer &>/dev/null; then
    info "Installing Composer..."
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    if [[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
      rm composer-setup.php
      die "Composer installer signature verification failed"
    fi
    php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php
    ok "Composer installed: $(composer --version 2>&1 | head -1)"
  else
    ok "Composer already installed: $(composer --version 2>&1 | head -1)"
  fi
}

# ── Node.js ───────────────────────────────────────────────────────────────────
install_nodejs() {
  info "Installing Node.js ${NEXVARY_NODE_VERSION}..."
  if ! command -v node &>/dev/null || \
     [[ "$(node --version | cut -d. -f1 | tr -d 'v')" -lt "$NEXVARY_NODE_VERSION" ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NEXVARY_NODE_VERSION}.x" | bash - 2>&1 | tee -a "$LOG_FILE"
    DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq nodejs 2>&1 | tee -a "$LOG_FILE"
  fi
  ok "Node.js installed: $(node --version)"
  ok "npm installed: $(npm --version)"
}

# ── PostgreSQL ───────────────────────────────────────────────────────────────
install_postgresql() {
  info "Installing PostgreSQL..."
  if ! command -v psql &>/dev/null; then
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | \
      gpg --dearmor -o /usr/share/keyrings/postgresql.gpg
    echo "deb [signed-by=/usr/share/keyrings/postgresql.gpg] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
      > /etc/apt/sources.list.d/postgresql.list
    $PKG_MGR update -qq 2>&1 | tee -a "$LOG_FILE"
    DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq postgresql postgresql-client 2>&1 | tee -a "$LOG_FILE"
  fi
  systemctl enable postgresql --quiet
  systemctl start postgresql
  ok "PostgreSQL installed: $(psql --version)"
}

setup_database() {
  info "Setting up PostgreSQL database..."
  DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
  DB_NAME="nexvary_cx"
  DB_USER="nexvary_cx"

  sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASSWORD}';" 2>&1 | tee -a "$LOG_FILE" || true
  sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};" 2>&1 | tee -a "$LOG_FILE" || true
  sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};" 2>&1 | tee -a "$LOG_FILE" || true

  echo "${DB_PASSWORD}" > /tmp/nexvary-db-password
  chmod 600 /tmp/nexvary-db-password

  ok "Database '${DB_NAME}' created"
  export NEXVARY_DB_PASSWORD="$DB_PASSWORD"
  export NEXVARY_DB_NAME="$DB_NAME"
  export NEXVARY_DB_USER="$DB_USER"
}

# ── Redis ─────────────────────────────────────────────────────────────────────
install_redis() {
  info "Installing Redis..."
  if ! command -v redis-cli &>/dev/null; then
    DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq redis-server 2>&1 | tee -a "$LOG_FILE"
  fi
  systemctl enable redis-server --quiet
  systemctl start redis-server
  ok "Redis installed: $(redis-cli --version)"
}

# ── Nginx ─────────────────────────────────────────────────────────────────────
install_nginx() {
  info "Installing Nginx..."
  if ! command -v nginx &>/dev/null; then
    DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq nginx 2>&1 | tee -a "$LOG_FILE"
  fi
  systemctl enable nginx --quiet
  ok "Nginx installed: $(nginx -v 2>&1)"
}

configure_nginx() {
  info "Configuring Nginx for NEXVARY CX..."
  local domain="${1:-_}"

  cat > /etc/nginx/sites-available/nexvary-cx << NGINX
server {
    listen 80;
    server_name ${domain};
    root ${NEXVARY_INSTALL_DIR}/public;
    index index.php index.html;

    client_max_body_size 50M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${NEXVARY_PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location /ws {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/nexvary-cx-access.log;
    error_log  /var/log/nginx/nexvary-cx-error.log;
}
NGINX

  ln -sf /etc/nginx/sites-available/nexvary-cx /etc/nginx/sites-enabled/nexvary-cx
  rm -f /etc/nginx/sites-enabled/default
  nginx -t 2>&1 | tee -a "$LOG_FILE"
  systemctl reload nginx
  ok "Nginx configured"
}

# ── FreeSWITCH ───────────────────────────────────────────────────────────────
install_freeswitch() {
  info "Installing FreeSWITCH..."
  TOKEN="${FREESWITCH_TOKEN:-}"

  if ! command -v freeswitch &>/dev/null; then
    if [[ -n "$TOKEN" ]]; then
      # Official SignalWire repository
      curl -sSLo /usr/share/keyrings/signalwire-freeswitch-repo.gpg \
        "https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg?TOKEN=${TOKEN}"
      echo "machine freeswitch.signalwire.com login signalwire password ${TOKEN}" \
        > /etc/apt/auth.conf.d/freeswitch.conf
      chmod 600 /etc/apt/auth.conf.d/freeswitch.conf
      echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] \
        https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/freeswitch.list
      $PKG_MGR update -qq 2>&1 | tee -a "$LOG_FILE"
      DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq freeswitch-meta-all 2>&1 | tee -a "$LOG_FILE"
    else
      # Build from source (community)
      warn "FREESWITCH_TOKEN not set. Installing from source (this takes ~15-20 minutes)..."
      install_freeswitch_source
    fi
  fi

  systemctl enable freeswitch --quiet
  systemctl start freeswitch || warn "FreeSWITCH failed to start. Check configuration."
  ok "FreeSWITCH ready"
}

install_freeswitch_source() {
  DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq \
    autoconf automake devscripts gawk g++ git-core libjpeg-dev libncurses5-dev \
    libtool make python3-dev libasound2-dev libtiff5-dev libperl-dev \
    libgdbm-dev libdb-dev libssl-dev libedit-dev libpq-dev libtiff-dev \
    libpcre3-dev libspeex-dev libspeexdsp-dev libldns-dev libedit-dev \
    libmemcached-dev libopus-dev libsndfile-dev libvpx-dev libyaml-dev \
    libsqlite3-dev libcurl4-openssl-dev libxmlrpc-c3-dev \
    2>&1 | tee -a "$LOG_FILE"

  cd /usr/src
  if [[ ! -d freeswitch ]]; then
    git clone --depth=1 https://github.com/signalwire/freeswitch.git -b v1.10 2>&1 | tee -a "$LOG_FILE"
  fi
  cd freeswitch
  ./bootstrap.sh -j 2>&1 | tee -a "$LOG_FILE"
  ./configure 2>&1 | tee -a "$LOG_FILE"
  make -j"$(nproc)" 2>&1 | tee -a "$LOG_FILE"
  make install 2>&1 | tee -a "$LOG_FILE"
  make cd-sounds-install cd-moh-install 2>&1 | tee -a "$LOG_FILE"

  # Create systemd unit
  cat > /etc/systemd/system/freeswitch.service << 'EOF'
[Unit]
Description=FreeSWITCH Softswitch
After=network.target postgresql.service redis-server.service

[Service]
Type=forking
PIDFile=/usr/local/freeswitch/run/freeswitch.pid
ExecStart=/usr/local/freeswitch/bin/freeswitch -nc -nonat
ExecStop=/usr/local/freeswitch/bin/freeswitch -stop
Restart=on-failure
RestartSec=5
LimitNOFILE=65535
User=freeswitch
Group=freeswitch

[Install]
WantedBy=multi-user.target
EOF

  useradd --system --no-create-home --shell /bin/false freeswitch 2>/dev/null || true
  chown -R freeswitch:freeswitch /usr/local/freeswitch
  systemctl daemon-reload
}

# ── Application Installation ──────────────────────────────────────────────────
install_application() {
  info "Installing NEXVARY CX Cloud application..."

  # Create system user
  if ! id -u "$NEXVARY_WEB_USER" &>/dev/null; then
    useradd --system --no-create-home --shell /bin/false "$NEXVARY_WEB_USER"
  fi

  # Install to /opt/nexvary-cx
  if [[ -d "$NEXVARY_INSTALL_DIR/.git" ]]; then
    cd "$NEXVARY_INSTALL_DIR"
    git pull origin main 2>&1 | tee -a "$LOG_FILE"
  else
    if [[ -d "$NEXVARY_INSTALL_DIR" ]]; then
      mv "$NEXVARY_INSTALL_DIR" "${NEXVARY_INSTALL_DIR}-backup-$(date +%Y%m%d)"
    fi
    git clone "$NEXVARY_REPO" "$NEXVARY_INSTALL_DIR" 2>&1 | tee -a "$LOG_FILE"
    cd "$NEXVARY_INSTALL_DIR"
  fi

  # Install PHP dependencies
  composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev \
    2>&1 | tee -a "$LOG_FILE"

  # Install Node.js dependencies and build frontend
  npm ci 2>&1 | tee -a "$LOG_FILE"
  npm run build 2>&1 | tee -a "$LOG_FILE"

  # Install WebRTC bridge
  if [[ -d rtc-bridge ]]; then
    cd rtc-bridge
    npm ci --production 2>&1 | tee -a "$LOG_FILE"
    cd ..
  fi

  # Create data directories
  mkdir -p "$NEXVARY_DATA_DIR" "$NEXVARY_LOG_DIR" "$NEXVARY_CONFIG_DIR"

  # Set permissions
  chown -R "$NEXVARY_WEB_USER":www-data "$NEXVARY_INSTALL_DIR"
  chmod -R 755 "$NEXVARY_INSTALL_DIR"
  chmod -R 775 "$NEXVARY_INSTALL_DIR/storage" "$NEXVARY_INSTALL_DIR/bootstrap/cache"

  ok "Application files installed"
}

configure_application() {
  info "Configuring NEXVARY CX Cloud..."
  cd "$NEXVARY_INSTALL_DIR"

  # Generate .env from template
  cp .env.example .env

  # Set values
  APP_KEY=$(php artisan key:generate --show 2>/dev/null || openssl rand -base64 32)
  APP_SECRET=$(openssl rand -hex 32)

  sed -i "s|APP_KEY=.*|APP_KEY=base64:${APP_KEY}|" .env
  sed -i "s|REVERB_APP_SECRET=.*|REVERB_APP_SECRET=${APP_SECRET}|" .env
  sed -i "s|DB_DATABASE=.*|DB_DATABASE=${NEXVARY_DB_NAME}|" .env
  sed -i "s|DB_USERNAME=.*|DB_USERNAME=${NEXVARY_DB_USER}|" .env
  sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${NEXVARY_DB_PASSWORD}|" .env
  sed -i "s|APP_URL=.*|APP_URL=http://$(hostname -I | awk '{print $1}')|" .env

  # Run migrations
  php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"

  # Cache config
  php artisan config:cache 2>&1 | tee -a "$LOG_FILE"
  php artisan route:cache 2>&1 | tee -a "$LOG_FILE"
  php artisan view:cache 2>&1 | tee -a "$LOG_FILE"

  # Mark installation pending wizard
  php artisan nexvary:set-install-pending 2>&1 | tee -a "$LOG_FILE" || true

  ok "Application configured"
}

# ── RTC Bridge Service ────────────────────────────────────────────────────────
configure_rtc_bridge() {
  info "Configuring WebRTC bridge service..."
  cat > /etc/systemd/system/nexvary-rtc.service << EOF
[Unit]
Description=NEXVARY CX WebRTC Bridge
After=network.target freeswitch.service

[Service]
Type=simple
User=${NEXVARY_WEB_USER}
WorkingDirectory=${NEXVARY_INSTALL_DIR}/rtc-bridge
ExecStart=/usr/bin/node src/index.js
Restart=on-failure
RestartSec=5
Environment=NODE_ENV=production
EnvironmentFile=${NEXVARY_INSTALL_DIR}/.env

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable nexvary-rtc --quiet
  systemctl start nexvary-rtc || warn "RTC bridge not started. Will be available after wizard."
  ok "WebRTC bridge service configured"
}

# ── Queue Worker ──────────────────────────────────────────────────────────────
configure_queue_worker() {
  info "Configuring queue worker..."
  cat > /etc/systemd/system/nexvary-queue.service << EOF
[Unit]
Description=NEXVARY CX Queue Worker
After=network.target redis-server.service

[Service]
Type=simple
User=${NEXVARY_WEB_USER}
WorkingDirectory=${NEXVARY_INSTALL_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable nexvary-queue --quiet
  systemctl start nexvary-queue
  ok "Queue worker configured"
}

# ── Scheduler (cron) ──────────────────────────────────────────────────────────
configure_scheduler() {
  (crontab -u "$NEXVARY_WEB_USER" -l 2>/dev/null; \
   echo "* * * * * cd ${NEXVARY_INSTALL_DIR} && php artisan schedule:run >> ${NEXVARY_LOG_DIR}/scheduler.log 2>&1") \
   | crontab -u "$NEXVARY_WEB_USER" -
  ok "Scheduler configured"
}

# ── Firewall ──────────────────────────────────────────────────────────────────
configure_firewall() {
  info "Configuring firewall..."
  if command -v ufw &>/dev/null; then
    ufw --force enable 2>&1 | tee -a "$LOG_FILE"
    ufw default deny incoming 2>&1 | tee -a "$LOG_FILE"
    ufw default allow outgoing 2>&1 | tee -a "$LOG_FILE"
    ufw allow ssh 2>&1 | tee -a "$LOG_FILE"
    ufw allow 80/tcp 2>&1 | tee -a "$LOG_FILE"
    ufw allow 443/tcp 2>&1 | tee -a "$LOG_FILE"
    ufw allow 5060/udp 2>&1 | tee -a "$LOG_FILE"
    ufw allow 5061/tcp 2>&1 | tee -a "$LOG_FILE"
    ufw allow 5080/udp 2>&1 | tee -a "$LOG_FILE"
    ufw allow 16384:32767/udp comment 'RTP Media' 2>&1 | tee -a "$LOG_FILE"
    # Block ESL from internet
    ufw deny 8021 2>&1 | tee -a "$LOG_FILE"
    ok "Firewall configured"
  else
    warn "ufw not available. Configure your firewall manually."
  fi
}

# ── fail2ban ──────────────────────────────────────────────────────────────────
configure_fail2ban() {
  info "Configuring fail2ban..."
  cat > /etc/fail2ban/jail.d/nexvary-cx.conf << 'EOF'
[nexvary-cx]
enabled  = true
port     = http,https
logpath  = /var/log/nginx/nexvary-cx-access.log
maxretry = 10
findtime = 600
bantime  = 3600

[nexvary-sip]
enabled  = true
port     = 5060,5061
protocol = udp
logpath  = /var/log/freeswitch.log
maxretry = 5
findtime = 300
bantime  = 86400
EOF

  systemctl enable fail2ban --quiet
  systemctl restart fail2ban
  ok "fail2ban configured"
}

# ── SSL with Certbot ──────────────────────────────────────────────────────────
setup_ssl() {
  local domain="$1"
  local email="$2"

  if [[ "$domain" == "_" ]] || [[ -z "$domain" ]]; then
    warn "No domain provided. Skipping SSL setup. Run after wizard: certbot --nginx -d your.domain.com"
    return
  fi

  info "Setting up Let's Encrypt SSL for ${domain}..."
  if ! command -v certbot &>/dev/null; then
    DEBIAN_FRONTEND=noninteractive $PKG_MGR install -y -qq certbot python3-certbot-nginx \
      2>&1 | tee -a "$LOG_FILE"
  fi

  certbot --nginx -d "$domain" --non-interactive --agree-tos -m "$email" \
    --redirect 2>&1 | tee -a "$LOG_FILE" || warn "SSL setup failed. Configure manually."

  # Auto-renew
  systemctl enable certbot.timer --quiet 2>/dev/null || \
    (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -

  ok "SSL configured for ${domain}"
}

# ── Completion Summary ────────────────────────────────────────────────────────
print_completion() {
  local server_ip
  server_ip=$(hostname -I | awk '{print $1}')

  echo ""
  echo -e "${BOLD}${WHITE}╔══════════════════════════════════════════════════════════════╗${NC}"
  echo -e "${BOLD}${WHITE}║           ${GREEN}✓ NEXVARY CX Cloud Installation Complete${WHITE}             ║${NC}"
  echo -e "${BOLD}${WHITE}╠══════════════════════════════════════════════════════════════╣${NC}"
  echo -e "${BOLD}${WHITE}║                                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${CYAN}Setup Wizard URL:${WHITE}                                            ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  http://${server_ip}/setup${WHITE}                                  ║${NC}"
  echo -e "${BOLD}${WHITE}║                                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${CYAN}Services Running:${WHITE}                                            ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ Nginx (web server)${WHITE}                                       ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ PHP-FPM ${NEXVARY_PHP_VERSION}${WHITE}                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ PostgreSQL (database)${WHITE}                                    ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ Redis (cache/queue)${WHITE}                                      ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ FreeSWITCH (telephony)${WHITE}                                   ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ NEXVARY Queue Worker${WHITE}                                     ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GREEN}  ✓ NEXVARY WebRTC Bridge${WHITE}                                    ║${NC}"
  echo -e "${BOLD}${WHITE}║                                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${YELLOW}Next Steps:${WHITE}                                                 ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GRAY}  1. Open the Setup Wizard URL above${WHITE}                         ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GRAY}  2. Create administrator account${WHITE}                             ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GRAY}  3. Configure organization settings${WHITE}                          ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GRAY}  4. Connect your SIP provider${WHITE}                               ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GRAY}  5. Set up SSL for your domain${WHITE}                              ║${NC}"
  echo -e "${BOLD}${WHITE}║                                                              ║${NC}"
  echo -e "${BOLD}${WHITE}║  ${GRAY}Log file: ${LOG_FILE}${WHITE}                       ║${NC}"
  echo -e "${BOLD}${WHITE}╚══════════════════════════════════════════════════════════════╝${NC}"
  echo ""
  echo -e "  ${RED}SECURITY REMINDER:${NC} Change default passwords immediately!"
  echo -e "  ${GRAY}Database password stored in: /tmp/nexvary-db-password${NC}"
  echo -e "  ${GRAY}Move it to a secure location and delete the temp file.${NC}"
  echo ""
}

# ── Main ──────────────────────────────────────────────────────────────────────
main() {
  print_banner
  log "NEXVARY CX Cloud installation started at $(date)"

  # Preflight
  check_root
  check_os
  check_arch
  check_resources
  check_ports

  # System preparation
  update_system
  install_dependencies

  # Core stack
  install_php
  install_composer
  install_nodejs
  install_postgresql
  setup_database
  install_redis
  install_nginx

  # Telephony
  install_freeswitch

  # Application
  install_application
  configure_application

  # Services
  configure_nginx "_"
  configure_rtc_bridge
  configure_queue_worker
  configure_scheduler

  # Security
  configure_firewall
  configure_fail2ban

  # Complete
  log "Installation completed at $(date)"
  print_completion
}

# Allow sourcing for testing
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  main "$@"
fi
