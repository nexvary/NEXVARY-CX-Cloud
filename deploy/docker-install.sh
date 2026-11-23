#!/usr/bin/env bash
# NEXVARY CX Cloud — Docker installation script
#
# Usage (from the repository root):
#   sudo bash deploy/docker-install.sh --domain=your.domain.com [OPTIONS]
#
# Options:
#   --domain=FQDN          Required. The public hostname for this instance.
#   --port=N               HTTP port for the app container (default: 8080).
#   --skip-nginx           Skip automated Nginx configuration.
#   --skip-firewall        Skip ufw rule management.
#   --rotate-credentials   Explicitly rotate all service credentials.
#                          DANGEROUS: existing encrypted data may become unreadable.
#   --yes                  Non-interactive mode.
#   --help                 Show this help.
#
# Safety guarantees:
#   - Credentials are generated ONLY on first installation.
#   - Re-running the installer on an existing deployment is safe and idempotent.
#   - APP_KEY is never rotated without explicit --rotate-credentials.
#   - PostgreSQL and Redis do NOT publish host ports (no conflict with host services).
#   - FreeSWITCH is NEVER compiled on the customer server; it is pulled from GHCR.
#   - The installer exits nonzero if the FreeSWITCH image is unavailable.

set -euo pipefail

# ── Colour helpers ─────────────────────────────────────────────────────────────
_tty() { [[ -t 1 ]] && printf '%s' "$1" || printf ''; }
RED="$(_tty '\033[0;31m')"   YLW="$(_tty '\033[0;33m')"
GRN="$(_tty '\033[0;32m')"   BLD="$(_tty '\033[1m')"
RST="$(_tty '\033[0m')"

nx_ok()    { printf '%s[OK]%s   %s\n' "$GRN" "$RST" "$*"; }
nx_warn()  { printf '%s[WARN]%s %s\n' "$YLW" "$RST" "$*"; }
nx_error() { printf '%s[ERR]%s  %s\n' "$RED" "$RST" "$*" >&2; }
nx_info()  { printf '%s[..] %s%s\n'   "$BLD" "$*" "$RST"; }
nx_fatal() { nx_error "$*"; exit 1; }
nx_step()  { printf '\n%s══ %s%s\n' "$BLD" "$*" "$RST"; }

# ── Parse arguments ────────────────────────────────────────────────────────────
DOMAIN=''
HTTP_PORT=8080
SKIP_NGINX='no'
SKIP_FIREWALL='no'
ROTATE_CREDENTIALS='no'
AUTO_YES='no'

for _arg in "$@"; do
  case "$_arg" in
    --domain=*)            DOMAIN="${_arg#*=}" ;;
    --port=*)              HTTP_PORT="${_arg#*=}" ;;
    --skip-nginx)          SKIP_NGINX='yes' ;;
    --skip-firewall)       SKIP_FIREWALL='yes' ;;
    --rotate-credentials)  ROTATE_CREDENTIALS='yes' ;;
    --yes|-y)              AUTO_YES='yes' ;;
    --help|-h)
      sed -n '/^# Usage/,/^[^#]/p' "$0" | grep '^#' | sed 's/^# \?//'
      exit 0 ;;
    *) nx_fatal "Unknown option: ${_arg}. Run with --help for usage." ;;
  esac
done
unset _arg

# ── Root check ─────────────────────────────────────────────────────────────────
[[ "$(id -u)" -eq 0 ]] || nx_fatal "Run as root: sudo bash deploy/docker-install.sh --domain=..."

# ── Working directory ─────────────────────────────────────────────────────────
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_DIR"
[[ -f 'compose.yaml' ]] || nx_fatal "compose.yaml not found — run from the repository root."

nx_step "NEXVARY CX Cloud — Docker Installer"
nx_info "Repository: ${REPO_DIR}"

# ── Domain validation ─────────────────────────────────────────────────────────
if [[ -z "$DOMAIN" ]]; then
  nx_error "--domain=FQDN is required."
  nx_error "Example: sudo bash deploy/docker-install.sh --domain=cx.yourcompany.com"
  exit 1
fi
if ! printf '%s' "$DOMAIN" | grep -qE '^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$'; then
  nx_fatal "Invalid domain: '${DOMAIN}'. Provide a fully qualified domain name."
fi
nx_ok "Domain: ${DOMAIN}"

# ── OS detection ──────────────────────────────────────────────────────────────
nx_step "Pre-flight checks"
[[ -f /etc/os-release ]] || nx_fatal "/etc/os-release not found — unsupported OS."
# shellcheck source=/dev/null
source /etc/os-release
OS_ID="${ID:-unknown}"
OS_VERSION="${VERSION_ID:-unknown}"
nx_info "OS: ${PRETTY_NAME:-${OS_ID} ${OS_VERSION}}"
case "$OS_ID" in
  debian) [[ "${OS_VERSION%%.*}" -lt 12 ]] && nx_warn "Debian ${OS_VERSION} detected; Debian 12+ recommended." ;;
  ubuntu) [[ "${OS_VERSION%%.*}" -lt 22 ]] && nx_warn "Ubuntu ${OS_VERSION} detected; Ubuntu 22.04+ recommended." ;;
  *)      nx_warn "OS '${OS_ID}' is not explicitly tested." ;;
esac

# ── Docker Engine ─────────────────────────────────────────────────────────────
_install_docker() {
  nx_info "Installing Docker Engine via get.docker.com..."
  curl -fsSL https://get.docker.com | sh
  systemctl enable --now docker
}

if ! command -v docker &>/dev/null; then
  nx_warn "Docker Engine not found."
  if [[ "$AUTO_YES" == 'yes' ]]; then _install_docker
  else
    read -r -p "${YLW}Install Docker Engine now? [Y/n]${RST} " _ans
    [[ "${_ans:-Y}" =~ ^[Yy]$ ]] && _install_docker || nx_fatal "Docker Engine is required."
  fi
fi
DOCKER_VERSION="$(docker version --format '{{.Server.Version}}' 2>/dev/null || echo 'unknown')"
nx_ok "Docker Engine ${DOCKER_VERSION}"

# ── Docker Compose v2 ─────────────────────────────────────────────────────────
_install_compose() {
  nx_info "Installing Docker Compose v2 plugin..."
  COMPOSE_VERSION='2.27.1'
  COMPOSE_ARCH="$(uname -m)"
  [[ "$COMPOSE_ARCH" == 'aarch64' ]] && COMPOSE_ARCH='aarch64' || COMPOSE_ARCH='x86_64'
  mkdir -p /usr/local/lib/docker/cli-plugins
  curl -SL "https://github.com/docker/compose/releases/download/v${COMPOSE_VERSION}/docker-compose-linux-${COMPOSE_ARCH}" \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
  chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
}

if ! docker compose version &>/dev/null; then
  nx_warn "Docker Compose v2 plugin not found."
  if [[ "$AUTO_YES" == 'yes' ]]; then _install_compose
  else
    read -r -p "${YLW}Install Docker Compose v2 now? [Y/n]${RST} " _ans
    [[ "${_ans:-Y}" =~ ^[Yy]$ ]] && _install_compose || nx_fatal "Docker Compose v2 is required."
  fi
fi
COMPOSE_VERSION="$(docker compose version --short 2>/dev/null || echo 'unknown')"
nx_ok "Docker Compose v${COMPOSE_VERSION}"

# ── DNS resolution check ───────────────────────────────────────────────────────
nx_step "DNS and connectivity checks"
if ! host "$DOMAIN" &>/dev/null && ! nslookup "$DOMAIN" &>/dev/null; then
  nx_fatal "DNS: '${DOMAIN}' does not resolve. Create an A/AAAA record pointing to this server before continuing."
fi
# Verify the DNS resolves to this server's IP
SERVER_IP="$(curl -4s https://checkip.amazonaws.com 2>/dev/null | tr -d '[:space:]' || echo '')"
DOMAIN_IP="$(host -t A "$DOMAIN" 2>/dev/null | awk '/has address/ {print $4; exit}' || echo '')"
if [[ -n "$SERVER_IP" && -n "$DOMAIN_IP" && "$SERVER_IP" != "$DOMAIN_IP" ]]; then
  nx_warn "DNS: ${DOMAIN} resolves to ${DOMAIN_IP}, but this server's IP appears to be ${SERVER_IP}."
  nx_warn "TLS certificate issuance will fail unless DNS is correct."
fi
nx_ok "DNS: ${DOMAIN} resolves to ${DOMAIN_IP:-unknown}"

# ── Detect host services (inform only — no ports published for postgres/redis) ─
nx_step "Detecting host services"
_port_in_use() { ss -ltnp 2>/dev/null | grep -q ":${1}\b"; }

if systemctl is-active nginx &>/dev/null 2>&1; then
  nx_info "Host Nginx detected — installer will add a site config."
else
  nx_warn "Nginx is not running. Install and start Nginx before continuing."
  if [[ "$SKIP_NGINX" == 'no' ]]; then
    if [[ "$AUTO_YES" == 'yes' ]]; then
      apt-get install -y nginx
      systemctl enable --now nginx
    else
      read -r -p "${YLW}Install and start Nginx now? [Y/n]${RST} " _ans
      if [[ "${_ans:-Y}" =~ ^[Yy]$ ]]; then
        apt-get install -y nginx
        systemctl enable --now nginx
      else
        nx_fatal "Nginx is required. Install it and re-run."
      fi
    fi
  fi
fi

# SIP/RTP conflicts are fatal and cannot be bypassed
if systemctl is-active freeswitch &>/dev/null 2>&1; then
  nx_error "Host FreeSWITCH is active. The Docker FreeSWITCH uses network_mode: host"
  nx_error "and will conflict with the existing instance on SIP (5060) and RTP ports."
  nx_fatal "Stop the host FreeSWITCH (systemctl stop freeswitch && systemctl disable freeswitch) first."
fi
for _p in 5060 5066; do
  if _port_in_use "$_p"; then
    nx_fatal "Port ${_p} is in use. This will conflict with FreeSWITCH SIP. Stop the conflicting process first."
  fi
done
if _port_in_use "${HTTP_PORT}"; then
  nx_fatal "Port ${HTTP_PORT} is already in use on 127.0.0.1. Use --port=ALTERNATIVE_PORT."
fi
nx_ok "No blocking port conflicts found"

# ── Firewall ──────────────────────────────────────────────────────────────────
if [[ "$SKIP_FIREWALL" == 'no' ]] && command -v ufw &>/dev/null && ufw status | grep -q 'Status: active'; then
  nx_step "Configuring ufw firewall"
  ufw allow 5060/tcp comment 'NEXVARY SIP'    2>/dev/null || true
  ufw allow 5060/udp comment 'NEXVARY SIP'    2>/dev/null || true
  ufw allow 5061/tcp comment 'NEXVARY SIP-TLS' 2>/dev/null || true
  ufw allow 5066/tcp comment 'NEXVARY SIP-WS' 2>/dev/null || true
  ufw allow 7443/tcp comment 'NEXVARY SIP-WSS' 2>/dev/null || true
  ufw allow 16384:16484/udp comment 'NEXVARY RTP' 2>/dev/null || true
  ufw allow 80/tcp  comment 'NEXVARY HTTP'    2>/dev/null || true
  ufw allow 443/tcp comment 'NEXVARY HTTPS'   2>/dev/null || true
  # ESL (8021) must NOT be open to the internet — ACL + ufw both block it
  ufw deny  8021/tcp comment 'NEXVARY ESL (internal only)' 2>/dev/null || true
  nx_ok "ufw rules applied (8021 blocked externally)"
fi

# ── Determine installation mode ────────────────────────────────────────────────
nx_step "Checking installation state"
mkdir -p "${REPO_DIR}/deploy"
ENV_FILE="${REPO_DIR}/deploy/.env"

FIRST_INSTALL='yes'
if [[ -f "$ENV_FILE" ]]; then
  EXISTING_KEY="$(grep '^APP_KEY=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '[:space:]' || echo '')"
  if [[ -n "$EXISTING_KEY" && "$EXISTING_KEY" != 'base64:' ]]; then
    FIRST_INSTALL='no'
    if [[ "$ROTATE_CREDENTIALS" == 'yes' ]]; then
      nx_warn "CREDENTIAL ROTATION requested (--rotate-credentials)."
      nx_warn "All encrypted application data will become unreadable after rotation."
      if [[ "$AUTO_YES" == 'no' ]]; then
        read -r -p "${RED}Continue with credential rotation? [yes/no]${RST} " _ans
        [[ "$_ans" == 'yes' ]] || nx_fatal "Rotation cancelled."
      fi
      BACKUP="${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"
      cp "$ENV_FILE" "$BACKUP"
      nx_warn "Old credentials backed up to ${BACKUP}"
      FIRST_INSTALL='yes'
    else
      nx_ok "Existing installation detected — credentials preserved"
      nx_info "To rotate credentials, run with --rotate-credentials (WARNING: breaks encrypted data)"
    fi
  fi
fi

# ── Generate or preserve secrets ──────────────────────────────────────────────
if [[ "$FIRST_INSTALL" == 'yes' ]]; then
  nx_step "Generating secrets (first install)"
  _gen_secret() { openssl rand -base64 32 | tr -d '/+=\n' | head -c "${1:-40}"; }
  _gen_hex()    { openssl rand -hex "${1:-16}"; }

  APP_KEY="base64:$(openssl rand -base64 32)"
  DB_PASSWORD="$(_gen_secret 40)"
  REDIS_PASSWORD="$(_gen_secret 40)"
  FS_ESL_PASSWORD="$(_gen_secret 40)"
  RTC_BRIDGE_TOKEN="$(_gen_hex 32)"
  REVERB_APP_KEY="$(_gen_hex 16)"
  REVERB_APP_SECRET="$(_gen_hex 32)"
  INSTALL_SECRET="$(_gen_hex 32)"

  sed \
    -e "s|DOMAIN_PLACEHOLDER|${DOMAIN}|g" \
    -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
    -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
    -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD}|" \
    -e "s|^FREESWITCH_ESL_PASSWORD=.*|FREESWITCH_ESL_PASSWORD=${FS_ESL_PASSWORD}|" \
    -e "s|^RTC_BRIDGE_TOKEN=.*|RTC_BRIDGE_TOKEN=${RTC_BRIDGE_TOKEN}|" \
    -e "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=${REVERB_APP_KEY}|" \
    -e "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=${REVERB_APP_SECRET}|" \
    -e "s|^NEXVARY_INSTALL_SECRET=.*|NEXVARY_INSTALL_SECRET=${INSTALL_SECRET}|" \
    -e "s|^NEXVARY_HTTP_PORT=.*|NEXVARY_HTTP_PORT=${HTTP_PORT}|" \
    "${REPO_DIR}/.env.docker.example" > "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  nx_ok "deploy/.env written (chmod 600)"

  # Persist ESL password for host-side fs_cli access
  mkdir -p /etc/nexvary
  printf 'FREESWITCH_ESL_PASSWORD=%s\n' "${FS_ESL_PASSWORD}" > /etc/nexvary/freeswitch.conf
  chmod 640 /etc/nexvary/freeswitch.conf
  nx_ok "ESL password saved to /etc/nexvary/freeswitch.conf"
else
  # Read the existing ESL password for health check use later
  FS_ESL_PASSWORD="$(grep '^FREESWITCH_ESL_PASSWORD=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '[:space:]' || echo '')"
  nx_ok "Existing deploy/.env preserved"
fi

# ── Pull FreeSWITCH image from GHCR ───────────────────────────────────────────
# FreeSWITCH is NEVER built on the customer server.
# The image is built by GitHub Actions and published to GHCR.
# If the image is unavailable, the installation stops here.
nx_step "Pulling FreeSWITCH image from GHCR"
FS_IMAGE='ghcr.io/nexvary/nexvary-cx-freeswitch:1.10.11'
nx_info "Pulling ${FS_IMAGE} ..."
if ! docker pull "${FS_IMAGE}"; then
  nx_error "Failed to pull FreeSWITCH image: ${FS_IMAGE}"
  nx_error "The image must be published to GHCR by the GitHub Actions build workflow before installation."
  nx_error "Check https://github.com/nexvary/NEXVARY-CX-Cloud/pkgs/container/nexvary-cx-freeswitch"
  exit 1
fi
nx_ok "FreeSWITCH image pulled: ${FS_IMAGE}"

# ── Build app images ──────────────────────────────────────────────────────────
nx_step "Building application images"
# Only build app and rtc-bridge — freeswitch must come from GHCR
docker compose --env-file "$ENV_FILE" build app rtc-bridge
nx_ok "Application images built"

# ── Automated Nginx configuration ─────────────────────────────────────────────
if [[ "$SKIP_NGINX" == 'no' ]]; then
  nx_step "Configuring host Nginx TLS reverse proxy"

  NGINX_SITE="/etc/nginx/sites-available/nexvary-cx-${DOMAIN}"
  NGINX_ENABLED="/etc/nginx/sites-enabled/nexvary-cx-${DOMAIN}"
  NGINX_BACKUP_DIR="/etc/nginx/sites-available.bak.$(date +%Y%m%d%H%M%S)"

  # Generate the site config from the template
  mkdir -p /var/www/letsencrypt
  sed \
    -e "s|DOMAIN_PLACEHOLDER|${DOMAIN}|g" \
    -e "s|HTTP_PORT_PLACEHOLDER|${HTTP_PORT}|g" \
    "${REPO_DIR}/deploy/nginx-proxy.conf.example" > "${NGINX_SITE}.new"

  # First-time Nginx config: drop the SSL stanzas until certbot adds them
  # Create an HTTP-only config for ACME challenge; certbot will upgrade to HTTPS
  cat > "${NGINX_SITE}.http-only" <<NGINXHTTP
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    location / {
        proxy_pass         http://127.0.0.1:${HTTP_PORT};
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto http;
        proxy_set_header   X-Forwarded-Host  \$host;
        proxy_read_timeout 90;
        client_max_body_size 50M;
    }

    location /ws {
        proxy_pass         http://127.0.0.1:${HTTP_PORT};
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    \$http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host       \$host;
        proxy_read_timeout 86400;
    }
}
NGINXHTTP

  # Determine if we have an existing valid cert or need a new one
  CERT_PATH="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
  HAVE_CERT='no'
  if [[ -f "$CERT_PATH" ]]; then
    # Verify cert is still valid (not expiring within 7 days)
    if openssl x509 -checkend $((7 * 86400)) -noout -in "$CERT_PATH" 2>/dev/null; then
      HAVE_CERT='yes'
      nx_ok "Existing TLS certificate found and valid: ${CERT_PATH}"
    else
      nx_warn "Existing TLS certificate is expiring within 7 days — will renew."
    fi
  fi

  # Backup existing site config if present
  if [[ -f "$NGINX_SITE" ]]; then
    mkdir -p "$NGINX_BACKUP_DIR"
    cp "$NGINX_SITE" "${NGINX_BACKUP_DIR}/"
    nx_info "Backed up existing Nginx config to ${NGINX_BACKUP_DIR}/"
  fi

  # Install HTTP-only config first (needed for ACME challenge)
  cp "${NGINX_SITE}.http-only" "$NGINX_SITE"
  ln -sf "$NGINX_SITE" "$NGINX_ENABLED"

  # Test and reload Nginx with HTTP-only config
  nginx -t 2>&1 | tail -3 || nx_fatal "Nginx config test failed — check /etc/nginx/sites-available/"
  systemctl reload nginx
  nx_ok "Nginx reloaded with HTTP-only config for ACME"

  if [[ "$HAVE_CERT" == 'no' ]]; then
    # Install certbot if needed
    if ! command -v certbot &>/dev/null; then
      nx_info "Installing certbot..."
      apt-get install -y certbot python3-certbot-nginx 2>/dev/null || \
        apt-get install -y certbot 2>/dev/null || \
        snap install --classic certbot && ln -sf /snap/bin/certbot /usr/bin/certbot
    fi
    nx_info "Requesting TLS certificate from Let's Encrypt for ${DOMAIN}..."
    # Use webroot so we don't need to stop Nginx
    if ! certbot certonly \
        --webroot \
        --webroot-path /var/www/letsencrypt \
        --non-interactive \
        --agree-tos \
        --email "admin@${DOMAIN}" \
        -d "$DOMAIN" 2>&1; then
      # Restore backup on failure
      if [[ -f "${NGINX_BACKUP_DIR}/${NGINX_SITE##*/}" ]]; then
        cp "${NGINX_BACKUP_DIR}/${NGINX_SITE##*/}" "$NGINX_SITE"
        systemctl reload nginx
      fi
      nx_fatal "Let's Encrypt certificate issuance failed. Verify DNS, port 80 accessibility, and retry."
    fi
    HAVE_CERT='yes'
    nx_ok "TLS certificate issued for ${DOMAIN}"
  fi

  if [[ "$HAVE_CERT" == 'yes' ]]; then
    # Switch to full HTTPS config
    cp "${NGINX_SITE}.new" "$NGINX_SITE"
    if nginx -t 2>&1 | tail -3 | grep -q error; then
      nx_warn "Nginx HTTPS config test has errors — keeping HTTP-only config."
      cp "${NGINX_SITE}.http-only" "$NGINX_SITE"
    fi
    systemctl reload nginx
    nx_ok "Nginx configured for HTTPS at https://${DOMAIN}"
  fi

  # Clean up temp files
  rm -f "${NGINX_SITE}.new" "${NGINX_SITE}.http-only"

  # Set up certbot renewal hook to reload Nginx
  mkdir -p /etc/letsencrypt/renewal-hooks/deploy
  cat > /etc/letsencrypt/renewal-hooks/deploy/nexvary-reload-nginx.sh <<'HOOK'
#!/bin/sh
systemctl reload nginx
HOOK
  chmod +x /etc/letsencrypt/renewal-hooks/deploy/nexvary-reload-nginx.sh
  nx_ok "certbot renewal hook installed"
fi

# ── Start the stack ────────────────────────────────────────────────────────────
nx_step "Starting NEXVARY CX Cloud stack"
docker compose --env-file "$ENV_FILE" up --detach --remove-orphans
nx_ok "Stack started"

# ── Wait for health checks ─────────────────────────────────────────────────────
nx_step "Waiting for services to become healthy"

_wait_healthy() {
  local svc="$1" timeout="${2:-120}" elapsed=0
  local cid
  cid="$(docker compose --env-file "$ENV_FILE" ps -q "$svc" 2>/dev/null | head -1)"
  if [[ -z "$cid" ]]; then
    # FreeSWITCH on network_mode:host won't appear in `ps -q` the same way
    nx_warn "${svc}: container ID not found via compose ps -q — checking via docker ps"
    cid="$(docker ps --filter "name=nexvary" --filter "name=${svc}" --format '{{.ID}}' | head -1)"
  fi
  while [[ $elapsed -lt $timeout ]]; do
    local state
    if [[ -n "$cid" ]]; then
      state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' "$cid" 2>/dev/null || echo 'unknown')"
    else
      state='unknown'
    fi
    case "$state" in
      healthy)        nx_ok "${svc}: healthy"; return 0 ;;
      no-healthcheck) nx_ok "${svc}: running (no healthcheck)"; return 0 ;;
      unhealthy)
        nx_error "${svc}: unhealthy after ${elapsed}s"
        docker compose --env-file "$ENV_FILE" logs --tail=30 "$svc" >&2 || true
        return 1 ;;
    esac
    sleep 5; elapsed=$((elapsed + 5))
    printf '  [..] Waiting for %s (%ds)...\r' "$svc" "$elapsed"
  done
  nx_error "${svc} did not become healthy within ${timeout}s"
  docker compose --env-file "$ENV_FILE" logs --tail=30 "$svc" >&2 || true
  return 1
}

_wait_healthy postgres  60
_wait_healthy redis     30
_wait_healthy app      120
_wait_healthy rtc-bridge 60
_wait_healthy nginx     60

# FreeSWITCH healthcheck waits up to 150s (loads SIP profile + RTP engine)
nx_info "Waiting for FreeSWITCH (allow up to 150s for SIP stack startup)..."
_wait_healthy freeswitch 150 || {
  nx_warn "FreeSWITCH health check did not pass — stack is running but FreeSWITCH may still be starting."
  nx_warn "Check: docker compose --env-file deploy/.env logs freeswitch"
}

# ── Verify Laravel health endpoint ────────────────────────────────────────────
nx_step "Verifying application health"
APP_HEALTH="http://127.0.0.1:${HTTP_PORT}/up"
_APP_OK='no'
for _i in $(seq 1 24); do
  HTTP_STATUS="$(curl -so /dev/null -w '%{http_code}' "${APP_HEALTH}" 2>/dev/null || echo '000')"
  if [[ "$HTTP_STATUS" == '200' ]]; then
    _APP_OK='yes'
    nx_ok "Laravel health endpoint (/up): HTTP 200"
    break
  fi
  sleep 5
done
if [[ "$_APP_OK" == 'no' ]]; then
  nx_error "Laravel health endpoint returned HTTP ${HTTP_STATUS} — installation may be incomplete."
  docker compose --env-file "$ENV_FILE" logs --tail=50 app >&2 || true
  exit 1
fi

# ── Verify FreeSWITCH ESL ─────────────────────────────────────────────────────
if command -v fs_cli &>/dev/null && [[ -n "$FS_ESL_PASSWORD" ]]; then
  ESL_STATUS="$(fs_cli -H 127.0.0.1 -P 8021 -p "${FS_ESL_PASSWORD}" -x 'status' 2>/dev/null || echo '')"
  if echo "$ESL_STATUS" | grep -q 'UP'; then
    nx_ok "FreeSWITCH ESL: authenticated connection verified (UP)"
    # Check required modules
    for _mod in mod_sofia mod_event_socket mod_verto; do
      MOD_STATE="$(fs_cli -H 127.0.0.1 -P 8021 -p "${FS_ESL_PASSWORD}" -x "module_exists ${_mod}" 2>/dev/null || echo '')"
      [[ "$MOD_STATE" == 'true' ]] && nx_ok "FreeSWITCH: ${_mod} loaded" || nx_warn "FreeSWITCH: ${_mod} not loaded"
    done
  else
    nx_warn "FreeSWITCH ESL not reachable. FreeSWITCH may still be starting."
  fi
fi

# ── Completion summary ─────────────────────────────────────────────────────────
printf '\n'
printf '%s%s%s\n' "$GRN" "═══════════════════════════════════════════════════" "$RST"
printf '%s NEXVARY CX Cloud — Installation Complete %s\n' "$BLD" "$RST"
printf '%s%s%s\n' "$GRN" "═══════════════════════════════════════════════════" "$RST"
printf '\n'
if [[ "$SKIP_NGINX" == 'no' ]]; then
  printf '  Setup URL:   %shttps://%s%s\n' "$BLD" "$DOMAIN" "$RST"
else
  printf '  Setup URL:   %shttp://127.0.0.1:%s%s  (Nginx not configured)\n' "$BLD" "$HTTP_PORT" "$RST"
fi
printf '  Credentials: %s%s%s\n' "$BLD" "$ENV_FILE" "$RST"
printf '  Logs:        docker compose --env-file deploy/.env logs -f\n'
printf '  Restart:     docker compose --env-file deploy/.env restart\n'
printf '  Stop:        docker compose --env-file deploy/.env down\n'
printf '  Upgrade:     git pull && sudo bash deploy/docker-install.sh --domain=%s\n' "$DOMAIN"
printf '\n'
