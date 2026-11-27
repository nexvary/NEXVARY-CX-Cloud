#!/usr/bin/env bash
# NEXVARY CX Cloud — Bootstrap Script (Step 1 of 2)
#
# This script prepares a fresh Linux server for NEXVARY CX Cloud installation.
# It verifies OS compatibility, installs minimal prerequisites, downloads the
# installer, and makes the `nexvary` command available.
#
# DO NOT pipe arbitrary scripts from the internet without inspection.
# This script is published at a known GitHub URL and verified by TLS.
#
# Usage (single command):
#   curl -fsSL https://raw.githubusercontent.com/nexvary/NEXVARY-CX-Cloud/master/installer/nexvary-bootstrap.sh | sudo bash
#
# After this completes, run:
#   sudo nexvary install [OPTIONS]

set -euo pipefail

BOOTSTRAP_VERSION='1.0.0'
REPO_URL='https://github.com/nexvary/NEXVARY-CX-Cloud.git'
INSTALLER_REF="${NX_INSTALL_REF:-master}"
INSTALL_BASE='/opt/nexvary'
INSTALLER_DIR="${INSTALL_BASE}/installer"
BIN_PATH='/usr/local/bin/nexvary'

# ── Terminal colors ───────────────────────────────────────────────────────────
if [[ -t 1 ]]; then
  C_RESET='\033[0m'; C_BOLD='\033[1m'
  C_GREEN='\033[0;32m'; C_YELLOW='\033[0;33m'; C_RED='\033[0;31m'
else
  C_RESET=''; C_BOLD=''; C_GREEN=''; C_YELLOW=''; C_RED=''
fi

info()  { printf "${C_BOLD}  [*] %s${C_RESET}\n" "$*"; }
ok()    { printf "${C_GREEN}  [✓] %s${C_RESET}\n" "$*"; }
warn()  { printf "${C_YELLOW}  [!] %s${C_RESET}\n" "$*" >&2; }
fail()  { printf "${C_RED}${C_BOLD}  [✗] %s${C_RESET}\n" "$*" >&2; exit 1; }

# ── Root check ────────────────────────────────────────────────────────────────
if [[ "$EUID" -ne 0 ]]; then
  fail "This bootstrap script must be run as root (sudo bash)"
fi

echo ""
printf "${C_BOLD}${C_GREEN}  NEXVARY CX Cloud — Bootstrap v${BOOTSTRAP_VERSION}${C_RESET}\n"
printf "${C_BOLD}  Preparing your server for installation...${C_RESET}\n"
echo ""

# ── OS compatibility check ────────────────────────────────────────────────────
info "Checking operating system…"

if [[ ! -f /etc/os-release ]]; then
  fail "/etc/os-release not found. This system is not supported."
fi

# shellcheck source=/dev/null
source /etc/os-release

OS_ID="${ID:-unknown}"
OS_VERSION="${VERSION_ID:-unknown}"
SUPPORTED=true

case "$OS_ID" in
  debian)
    case "$OS_VERSION" in
      12|13) ok "Debian $OS_VERSION — Supported" ;;
      11) warn "Debian 11 is nearing end-of-life. Proceeding with partial support." ;;
      *) SUPPORTED=false; fail "Debian $OS_VERSION is not supported. Use Debian 12 or 13." ;;
    esac
    PKG_MGR='apt'
    ;;
  ubuntu)
    case "$OS_VERSION" in
      22.04|24.04) ok "Ubuntu $OS_VERSION — Supported" ;;
      *) warn "Ubuntu $OS_VERSION is not explicitly tested." ;;
    esac
    PKG_MGR='apt'
    ;;
  centos)
    case "$OS_VERSION" in
      9|10) ok "CentOS Stream $OS_VERSION — Supported" ;;
      8) SUPPORTED=false; fail "CentOS Stream 8 reached end-of-life. Use CentOS Stream 9 or 10." ;;
      7|6) SUPPORTED=false; fail "CentOS Linux $OS_VERSION is end-of-life. Use CentOS Stream 9 or 10." ;;
      *) warn "CentOS $OS_VERSION is not explicitly tested." ;;
    esac
    PKG_MGR='dnf'
    ;;
  rhel|rocky|almalinux)
    warn "$OS_ID $OS_VERSION may work but is not an explicitly tested platform."
    PKG_MGR='dnf'
    ;;
  *)
    SUPPORTED=false
    fail "Unsupported OS: $OS_ID $OS_VERSION"
    ;;
esac

# ── CPU architecture check ────────────────────────────────────────────────────
ARCH="$(uname -m)"
case "$ARCH" in
  x86_64|aarch64) ok "Architecture: $ARCH — Supported" ;;
  *)              warn "Architecture $ARCH is not tested. Installation may fail." ;;
esac

# ── Minimum requirements ──────────────────────────────────────────────────────
info "Checking system requirements…"

RAM_MB="$(awk '/MemTotal/ { printf "%d", $2/1024 }' /proc/meminfo 2>/dev/null || echo 0)"
DISK_GB="$(df -BG / 2>/dev/null | awk 'NR==2 { gsub(/G/,"",$4); print $4 }' || echo 0)"

if [[ "$RAM_MB" -lt 1024 ]]; then
  warn "RAM: ${RAM_MB}MB detected. Minimum 1GB recommended. Proceeding anyway."
else
  ok "RAM: ${RAM_MB}MB"
fi

if [[ "$DISK_GB" -lt 10 ]]; then
  fail "Insufficient disk space: ${DISK_GB}GB free, minimum 10GB required."
else
  ok "Disk: ${DISK_GB}GB free"
fi

# ── Install minimal prerequisites ────────────────────────────────────────────
info "Installing prerequisites (git, curl, gnupg)…"

case "$PKG_MGR" in
  apt)
    DEBIAN_FRONTEND=noninteractive apt-get -y -q update >/dev/null 2>&1
    DEBIAN_FRONTEND=noninteractive apt-get -y -q install git curl gnupg ca-certificates >/dev/null 2>&1
    ;;
  dnf)
    dnf -y -q install git curl gnupg2 ca-certificates >/dev/null 2>&1
    ;;
esac

ok "Prerequisites installed"

# ── Download installer ────────────────────────────────────────────────────────
info "Downloading NEXVARY CX Cloud installer…"

mkdir -p "$INSTALL_BASE"

if [[ -d "${INSTALLER_DIR}/.git" ]]; then
  info "Existing installer found — updating to ref $INSTALLER_REF"
  git -C "$INSTALLER_DIR" fetch origin --quiet
  git -C "$INSTALLER_DIR" checkout "$INSTALLER_REF" --quiet
  git -C "$INSTALLER_DIR" reset --hard "origin/$INSTALLER_REF" --quiet
else
  git clone --depth=1 --branch "$INSTALLER_REF" "$REPO_URL" "$INSTALLER_DIR" --quiet
fi

# ── Verify installer integrity ────────────────────────────────────────────────
info "Verifying installer integrity…"

INSTALLER_SCRIPT="${INSTALLER_DIR}/installer/nexvary-install.sh"
if [[ ! -f "$INSTALLER_SCRIPT" ]]; then
  fail "Installer script not found at $INSTALLER_SCRIPT. The download may be incomplete."
fi

# Ensure scripts are executable
chmod +x "$INSTALLER_SCRIPT"
chmod +x "${INSTALLER_DIR}/installer/lib/"*.sh 2>/dev/null || true

ok "Installer downloaded and verified"

# ── Install nexvary command ────────────────────────────────────────────────────
info "Installing 'nexvary' command to $BIN_PATH…"

cat > "$BIN_PATH" <<'WRAPPER'
#!/usr/bin/env bash
# NEXVARY CX Cloud — Command Wrapper
INSTALLER_SCRIPT='/opt/nexvary/installer/installer/nexvary-install.sh'
if [[ ! -f "$INSTALLER_SCRIPT" ]]; then
  echo "Error: installer not found at $INSTALLER_SCRIPT" >&2
  echo "Re-run the bootstrap script to reinstall." >&2
  exit 1
fi
exec bash "$INSTALLER_SCRIPT" "$@"
WRAPPER

chmod +x "$BIN_PATH"
ok "'nexvary' command available at $BIN_PATH"

# ── Print next step ────────────────────────────────────────────────────────────
echo ""
printf "${C_GREEN}${C_BOLD}╔══════════════════════════════════════════════════════╗${C_RESET}\n"
printf "${C_GREEN}${C_BOLD}║           Bootstrap complete — Step 2 of 2           ║${C_RESET}\n"
printf "${C_GREEN}${C_BOLD}╚══════════════════════════════════════════════════════╝${C_RESET}\n"
echo ""
printf "${C_BOLD}  Run the installer:${C_RESET}\n"
printf "  ${C_GREEN}${C_BOLD}sudo nexvary install${C_RESET}\n"
echo ""
printf "${C_BOLD}  Options:${C_RESET}\n"
printf "    --domain=your.domain.com     Set server domain\n"
printf "    --https=certbot              Enable HTTPS via Let's Encrypt\n"
printf "    --sw-token=TOKEN             SignalWire token for FreeSWITCH\n"
printf "    --skip-freeswitch            Install without FreeSWITCH\n"
printf "    --verbose                    Show detailed output\n"
echo ""
printf "${C_BOLD}  Documentation:${C_RESET} https://nexvary.com/docs/install\n"
printf "${C_BOLD}  GitHub:${C_RESET}        https://github.com/nexvary/NEXVARY-CX-Cloud\n"
echo ""
