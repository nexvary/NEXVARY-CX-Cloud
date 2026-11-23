#!/usr/bin/env bash
# NEXVARY CX Cloud — Docker installation script
#
# Usage (from the repository root):
#   sudo bash deploy/docker-install.sh --domain=your.domain.com [OPTIONS]
#
# Options:
#   --domain=FQDN          Required. The public hostname for this instance.
#   --port=N               HTTP port for the app container (default: 8080).
#                          Nginx on the host proxies HTTPS to 127.0.0.1:PORT.
#   --skip-firewall        Skip ufw/iptables open-port guidance.
#   --pull                 Pull latest images before starting (default: yes).
#   --no-pull              Skip image pull (use locally built images).
#   --yes                  Non-interactive: accept all prompts automatically.
#   --help                 Show this help.
#
# What this script does:
#   1. Checks root, OS, and Docker Engine / Compose v2 (installs if missing).
#   2. Detects existing host services (Nginx, PostgreSQL, Redis, FreeSWITCH)
#      and reports them without touching them.
#   3. Generates unique secrets (APP_KEY, DB_PASSWORD, REDIS_PASSWORD, ESL, etc.)
#   4. Writes deploy/.env from .env.docker.example substitutions.
#   5. Verifies that required ports are not already in use.
#   6. Pulls images, starts the Compose stack.
#   7. Waits for all services to pass their health checks.
#   8. Prints the setup URL and minimal post-install instructions.
#
# SAFETY:
#   - Never purges packages, drops databases, or modifies existing websites.
#   - On a server with existing Nginx, PostgreSQL, Redis or FreeSWITCH the
#     script reports each one and continues; the Docker stack uses different
#     port bindings (127.0.0.1:8080 for nginx, see --port).
#   - PostgreSQL and Redis host services might conflict on 5432/6379.
#     The script warns and exits; use --port or resolve the conflict first.

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
DO_PULL='yes'
AUTO_YES='no'
SKIP_FIREWALL='no'

for _arg in "$@"; do
  case "$_arg" in
    --domain=*)        DOMAIN="${_arg#*=}" ;;
    --port=*)          HTTP_PORT="${_arg#*=}" ;;
    --pull)            DO_PULL='yes' ;;
    --no-pull)         DO_PULL='no' ;;
    --skip-firewall)   SKIP_FIREWALL='yes' ;;
    --yes|-y)          AUTO_YES='yes' ;;
    --help|-h)
      sed -n '/^# Usage/,/^[^#]/p' "$0" | grep '^#' | sed 's/^# \?//'
      exit 0 ;;
    *) nx_fatal "Unknown option: ${_arg}. Run with --help for usage." ;;
  esac
done
unset _arg

# ── Root check ─────────────────────────────────────────────────────────────────
[[ "$(id -u)" -eq 0 ]] || nx_fatal "Run as root: sudo bash deploy/docker-install.sh --domain=..."

# ── Working directory must be repo root ───────────────────────────────────────
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_DIR"
[[ -f 'compose.yaml' ]] || nx_fatal "compose.yaml not found — run from repository root."

nx_step "NEXVARY CX Cloud — Docker Installer"
nx_info "Repository: ${REPO_DIR}"

# ── Domain validation ─────────────────────────────────────────────────────────
if [[ -z "$DOMAIN" ]]; then
  nx_error "--domain=FQDN is required."
  nx_error "Example: sudo bash deploy/docker-install.sh --domain=cx.yourcompany.com"
  exit 1
fi
# Basic FQDN sanity check
if ! printf '%s' "$DOMAIN" | grep -qE '^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$'; then
  nx_fatal "Invalid domain: '${DOMAIN}'. Provide a fully qualified domain name."
fi
nx_ok "Domain: ${DOMAIN}"

# ── OS detection ──────────────────────────────────────────────────────────────
nx_step "Pre-flight checks"
if [[ ! -f /etc/os-release ]]; then
  nx_fatal "/etc/os-release not found — unsupported OS."
fi
# shellcheck source=/dev/null
source /etc/os-release
OS_ID="${ID:-unknown}"
OS_VERSION="${VERSION_ID:-unknown}"
nx_info "OS: ${PRETTY_NAME:-${OS_ID} ${OS_VERSION}}"

case "$OS_ID" in
  debian)
    if [[ "${OS_VERSION%%.*}" -lt 12 ]]; then
      nx_warn "Debian ${OS_VERSION} detected. Debian 12+ recommended."
    fi ;;
  ubuntu)
    if [[ "${OS_VERSION%%.*}" -lt 22 ]]; then
      nx_warn "Ubuntu ${OS_VERSION} detected. Ubuntu 22.04+ recommended."
    fi ;;
  *)
    nx_warn "OS '${OS_ID}' is not explicitly tested. Proceeding anyway." ;;
esac

# ── Docker Engine ─────────────────────────────────────────────────────────────
_install_docker() {
  nx_info "Installing Docker Engine..."
  curl -fsSL https://get.docker.com | sh
  systemctl enable --now docker
}

if ! command -v docker &>/dev/null; then
  nx_warn "Docker Engine not found."
  if [[ "$AUTO_YES" == 'yes' ]]; then
    _install_docker
  else
    read -r -p "${YLW}Install Docker Engine now? [Y/n]${RST} " _ans
    if [[ "${_ans:-Y}" =~ ^[Yy]$ ]]; then _install_docker
    else nx_fatal "Docker Engine is required. Install it and re-run."; fi
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
    if [[ "${_ans:-Y}" =~ ^[Yy]$ ]]; then _install_compose
    else nx_fatal "Docker Compose v2 is required."; fi
  fi
fi
COMPOSE_VERSION="$(docker compose version --short 2>/dev/null || echo 'unknown')"
nx_ok "Docker Compose v${COMPOSE_VERSION}"

# ── Detect existing host services (report only — do not touch) ────────────────
nx_step "Detecting existing host services"

_port_in_use() { ss -ltnp 2>/dev/null | grep -q ":${1}\b"; }

if systemctl is-active nginx   &>/dev/null 2>&1; then nx_warn "Existing host Nginx detected — the app will bind to 127.0.0.1:${HTTP_PORT} only."; fi
if systemctl is-active postgresql &>/dev/null 2>&1; then
  if _port_in_use 5432; then
    nx_fatal "Host PostgreSQL is running and port 5432 is in use. The Docker PostgreSQL also needs port 5432 (127.0.0.1:5432). Stop the host service or choose a different deployment."
  fi
fi
if systemctl is-active redis-server &>/dev/null 2>&1 || systemctl is-active redis &>/dev/null 2>&1; then
  if _port_in_use 6379; then
    nx_fatal "Host Redis is running and port 6379 is in use. Stop the host Redis service or use --port for a custom deployment."
  fi
fi
if systemctl is-active freeswitch &>/dev/null 2>&1; then
  nx_error "Host FreeSWITCH is active. The Docker FreeSWITCH uses network_mode: host"
  nx_error "and will conflict with the existing instance on SIP (5060) and RTP ports."
  nx_fatal "Stop the host FreeSWITCH (systemctl stop freeswitch) before continuing."
fi
nx_ok "No blocking host service conflicts found"

# ── Port conflict check ────────────────────────────────────────────────────────
nx_step "Port conflict check"
PORT_CONFLICTS=0
for _p in 5060 8021 5066; do
  if _port_in_use "$_p"; then
    nx_warn "Port ${_p} is already in use — FreeSWITCH (host networking) will conflict."
    PORT_CONFLICTS=$((PORT_CONFLICTS + 1))
  fi
done
if _port_in_use "${HTTP_PORT}"; then
  nx_warn "Port ${HTTP_PORT} is already in use. The Nginx container will fail to bind."
  PORT_CONFLICTS=$((PORT_CONFLICTS + 1))
fi
if [[ $PORT_CONFLICTS -gt 0 ]]; then
  if [[ "$AUTO_YES" == 'yes' ]]; then
    nx_warn "Continuing despite port conflicts (--yes specified)."
  else
    read -r -p "${YLW}Port conflicts detected. Continue anyway? [y/N]${RST} " _ans
    [[ "${_ans:-N}" =~ ^[Yy]$ ]] || nx_fatal "Resolve port conflicts and re-run."
  fi
fi
nx_ok "Port check complete"

# ── Firewall guidance ─────────────────────────────────────────────────────────
if [[ "$SKIP_FIREWALL" == 'no' ]]; then
  nx_step "Firewall guidance"
  nx_info "Required open ports:"
  nx_info "  TCP/UDP 5060   — SIP signaling"
  nx_info "  TCP     5061   — SIP TLS"
  nx_info "  TCP     5066   — SIP over WebSocket (mod_sofia)"
  nx_info "  TCP     7443   — SIP over WebSocket/TLS"
  nx_info "  UDP  16384-16484 — RTP media (100 channels)"
  nx_info "  TCP     80/443 — HTTP/HTTPS (via host Nginx)"
  if command -v ufw &>/dev/null && ufw status | grep -q 'Status: active'; then
    nx_info "ufw is active — applying rules automatically..."
    ufw allow 5060/tcp comment 'NEXVARY SIP' 2>/dev/null || true
    ufw allow 5060/udp comment 'NEXVARY SIP' 2>/dev/null || true
    ufw allow 5061/tcp comment 'NEXVARY SIP-TLS' 2>/dev/null || true
    ufw allow 5066/tcp comment 'NEXVARY SIP-WS' 2>/dev/null || true
    ufw allow 7443/tcp comment 'NEXVARY SIP-WSS' 2>/dev/null || true
    ufw allow 16384:16484/udp comment 'NEXVARY RTP' 2>/dev/null || true
    nx_ok "ufw rules applied"
  fi
fi

# ── Generate secrets ──────────────────────────────────────────────────────────
nx_step "Generating secrets"

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

nx_ok "All secrets generated (not echoed)"

# ── Write deploy/.env ─────────────────────────────────────────────────────────
nx_step "Writing deploy/.env"
mkdir -p "${REPO_DIR}/deploy"
ENV_FILE="${REPO_DIR}/deploy/.env"

if [[ -f "$ENV_FILE" ]]; then
  BACKUP="${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"
  nx_warn "Existing deploy/.env found — backing up to ${BACKUP}"
  cp "$ENV_FILE" "$BACKUP"
fi

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

# ── Save FreeSWITCH ESL password where FreeSWITCH container can find it ───────
mkdir -p /etc/nexvary
printf 'FREESWITCH_ESL_PASSWORD=%s\n' "${FS_ESL_PASSWORD}" > /etc/nexvary/freeswitch.conf
chmod 640 /etc/nexvary/freeswitch.conf
nx_ok "ESL password saved to /etc/nexvary/freeswitch.conf"

# ── Host Nginx configuration hint ─────────────────────────────────────────────
nx_step "Host Nginx proxy configuration"
nx_info "The Docker Nginx will listen on 127.0.0.1:${HTTP_PORT}."
nx_info "Add a TLS virtual host to your host Nginx pointing to it."
nx_info "An example configuration has been generated:"
NGINX_EXAMPLE="${REPO_DIR}/deploy/nginx-proxy.${DOMAIN}.conf"
sed \
  -e "s|DOMAIN_PLACEHOLDER|${DOMAIN}|g" \
  -e "s|HTTP_PORT_PLACEHOLDER|${HTTP_PORT}|g" \
  "${REPO_DIR}/deploy/nginx-proxy.conf.example" > "$NGINX_EXAMPLE"
nx_info "  ${NGINX_EXAMPLE}"
nx_info "Review it, install to /etc/nginx/sites-available/, enable, then reload Nginx."

# ── Pull images ────────────────────────────────────────────────────────────────
nx_step "Pulling / building Docker images"
if [[ "$DO_PULL" == 'yes' ]]; then
  nx_info "Pulling service images..."
  docker compose --env-file "$ENV_FILE" pull --quiet --ignore-pull-failures 2>&1 | \
    grep -v 'Skipping' || true
fi

nx_info "Building application images..."
docker compose --env-file "$ENV_FILE" build --quiet

# ── Start the stack ────────────────────────────────────────────────────────────
nx_step "Starting NEXVARY CX Cloud stack"
docker compose --env-file "$ENV_FILE" up --detach --remove-orphans

# ── Wait for health checks ─────────────────────────────────────────────────────
nx_step "Waiting for services to become healthy"
_wait_healthy() {
  local svc="$1" timeout="${2:-120}" elapsed=0
  while [[ $elapsed -lt $timeout ]]; do
    local state
    state="$(docker compose --env-file "$ENV_FILE" ps --format json "$svc" 2>/dev/null \
             | grep -o '"Health":"[^"]*"' | head -1 | cut -d'"' -f4 || echo 'unknown')"
    case "$state" in
      healthy)   nx_ok "${svc}: healthy"; return 0 ;;
      unhealthy) nx_error "${svc}: unhealthy after ${elapsed}s"
                 docker compose --env-file "$ENV_FILE" logs --tail=30 "$svc" >&2
                 return 1 ;;
    esac
    sleep 5; elapsed=$((elapsed + 5))
    printf '  [..] Waiting for %s (%ds)...\r' "$svc" "$elapsed"
  done
  nx_error "${svc} did not become healthy within ${timeout}s"
  docker compose --env-file "$ENV_FILE" logs --tail=30 "$svc" >&2
  return 1
}

_wait_healthy postgres  60
_wait_healthy redis     30
_wait_healthy app      120
_wait_healthy nginx     60

# FreeSWITCH takes longer (loads SIP stack)
nx_info "Waiting for FreeSWITCH (may take 90s on first start)..."
_wait_healthy freeswitch 150 || nx_warn "FreeSWITCH is not yet healthy — check: docker compose logs freeswitch"

# ── Verify Laravel health endpoint ────────────────────────────────────────────
nx_step "Verifying application health"
APP_HEALTH="http://127.0.0.1:${HTTP_PORT}/up"
for _i in $(seq 1 12); do
  HTTP_STATUS="$(curl -so /dev/null -w '%{http_code}' "${APP_HEALTH}" 2>/dev/null || echo '000')"
  if [[ "$HTTP_STATUS" == '200' ]]; then
    nx_ok "Laravel health endpoint: ${HTTP_STATUS}"
    break
  fi
  if [[ $_i -eq 12 ]]; then
    nx_error "Laravel health endpoint returned ${HTTP_STATUS} — check logs."
    docker compose --env-file "$ENV_FILE" logs --tail=30 app >&2
  fi
  sleep 5
done

# ── Verify FreeSWITCH ESL ─────────────────────────────────────────────────────
if command -v fs_cli &>/dev/null; then
  ESL_STATUS="$(fs_cli -p "${FS_ESL_PASSWORD}" -x 'status' 2>/dev/null || echo '')"
  if echo "$ESL_STATUS" | grep -q 'UP'; then
    nx_ok "FreeSWITCH ESL: authenticated connection verified"
  else
    nx_warn "FreeSWITCH ESL not reachable from host (not critical if fs_cli is not installed on host)"
  fi
fi

# ── Print completion summary ───────────────────────────────────────────────────
printf '\n'
printf '%s%s%s\n' "$GRN" "═══════════════════════════════════════════════" "$RST"
printf '%s NEXVARY CX Cloud — Installation Complete %s\n' "$BLD" "$RST"
printf '%s%s%s\n' "$GRN" "═══════════════════════════════════════════════" "$RST"
printf '\n'
printf '  Web Setup URL:  %shttps://%s%s\n'                 "$BLD" "$DOMAIN" "$RST"
printf '  Admin panel:    %shttps://%s/admin%s\n'           "$BLD" "$DOMAIN" "$RST"
printf '  Container logs: %sdocker compose logs -f%s\n'     "$BLD" "$RST"
printf '  Restart stack:  %sdocker compose restart%s\n'     "$BLD" "$RST"
printf '  Stop stack:     %sdocker compose down%s\n'        "$BLD" "$RST"
printf '\n'
printf '  Credentials saved to:  %s%s%s\n'                  "$BLD" "$ENV_FILE" "$RST"
printf '\n'
printf '  Next step: Configure the host Nginx TLS proxy:\n'
printf '    %ssudo cp %s /etc/nginx/sites-available/nexvary-cx%s\n' \
        "$BLD" "$NGINX_EXAMPLE" "$RST"
printf '    %ssudo ln -s /etc/nginx/sites-available/nexvary-cx /etc/nginx/sites-enabled/%s\n' "$BLD" "$RST"
printf '    %ssudo nginx -t && sudo systemctl reload nginx%s\n'   "$BLD" "$RST"
printf '\n'
printf '  Then visit: %shttps://%s%s\n' "$BLD" "$DOMAIN" "$RST"
printf '  to complete the setup wizard.\n\n'
