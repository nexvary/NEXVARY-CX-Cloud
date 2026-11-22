#!/usr/bin/env bash
# OS and hardware detection module

set -euo pipefail

# ── Outputs (populated by nx_detect_os) ───────────────────────────────────────
NX_OS_ID=''         # e.g. debian, centos
NX_OS_CODENAME=''   # e.g. bookworm, trixie, stream9
NX_OS_VERSION=''    # e.g. 12, 13, 9, 10
NX_ARCH=''          # amd64 | arm64
NX_PKG_MGR=''       # apt | dnf
NX_SUPPORTED=''     # yes | partial | no

# Minimum requirements
readonly NX_MIN_RAM_MB=1024
readonly NX_MIN_DISK_GB=10
readonly NX_MIN_PHP='8.3'

# ── OS detection ──────────────────────────────────────────────────────────────

nx_detect_os() {
  if [[ ! -f /etc/os-release ]]; then
    NX_SUPPORTED='no'
    nx_error "/etc/os-release not found — cannot detect OS"
    return 1
  fi

  # shellcheck source=/dev/null
  source /etc/os-release

  NX_OS_ID="${ID:-unknown}"
  NX_OS_VERSION="${VERSION_ID:-unknown}"
  NX_OS_CODENAME="${VERSION_CODENAME:-}"

  NX_ARCH="$(uname -m)"
  case "$NX_ARCH" in
    x86_64)   NX_ARCH='amd64' ;;
    aarch64)  NX_ARCH='arm64' ;;
    *)        NX_ARCH="$NX_ARCH" ;;
  esac

  case "$NX_OS_ID" in
    debian)
      NX_PKG_MGR='apt'
      case "$NX_OS_VERSION" in
        12)
          NX_OS_CODENAME='bookworm'
          NX_SUPPORTED='yes'
          ;;
        13)
          NX_OS_CODENAME='trixie'
          NX_SUPPORTED='yes'
          ;;
        11)
          NX_OS_CODENAME='bullseye'
          NX_SUPPORTED='partial'
          nx_warn "Debian 11 (Bullseye) is nearing EOL. Consider upgrading to Debian 12."
          ;;
        *)
          NX_SUPPORTED='no'
          nx_warn "Debian $NX_OS_VERSION is not a supported release."
          ;;
      esac
      ;;

    ubuntu)
      NX_PKG_MGR='apt'
      case "$NX_OS_VERSION" in
        24.04) NX_OS_CODENAME='noble';    NX_SUPPORTED='yes' ;;
        22.04) NX_OS_CODENAME='jammy';    NX_SUPPORTED='yes' ;;
        *)
          NX_SUPPORTED='partial'
          nx_warn "Ubuntu $NX_OS_VERSION is not an explicitly tested release."
          ;;
      esac
      ;;

    centos)
      NX_PKG_MGR='dnf'
      # CentOS Stream sets VERSION_ID to 8, 9 or 10
      case "$NX_OS_VERSION" in
        9)
          NX_OS_CODENAME='stream9'
          NX_SUPPORTED='yes'
          ;;
        10)
          NX_OS_CODENAME='stream10'
          NX_SUPPORTED='yes'
          ;;
        8)
          # CentOS Stream 8 reached EOL 2024-05-31
          NX_SUPPORTED='no'
          nx_error "CentOS Stream 8 reached end-of-life on 2024-05-31 and is not supported."
          return 1
          ;;
        7|6|5)
          NX_SUPPORTED='no'
          nx_error "CentOS Linux $NX_OS_VERSION is end-of-life and not supported. Use CentOS Stream 9 or 10."
          return 1
          ;;
        *)
          NX_SUPPORTED='partial'
          nx_warn "CentOS version $NX_OS_VERSION is unrecognised."
          ;;
      esac
      ;;

    rhel|rocky|almalinux)
      NX_PKG_MGR='dnf'
      NX_SUPPORTED='partial'
      nx_warn "$NX_OS_ID $NX_OS_VERSION may work but is not an explicitly tested platform."
      ;;

    *)
      NX_SUPPORTED='no'
      nx_error "Unsupported OS: $NX_OS_ID $NX_OS_VERSION"
      return 1
      ;;
  esac
}

# ── Hardware checks ────────────────────────────────────────────────────────────

nx_check_hardware() {
  local ram_mb disk_gb ram_ok disk_ok
  ram_mb="$(awk '/MemTotal/ { printf "%d", $2/1024 }' /proc/meminfo 2>/dev/null || echo 0)"
  disk_gb="$(df -BG / 2>/dev/null | awk 'NR==2 { gsub(/G/,"",$4); print $4 }' || echo 0)"

  ram_ok='yes'; disk_ok='yes'
  if [[ "$ram_mb" -lt "$NX_MIN_RAM_MB" ]]; then
    nx_warn "RAM: ${ram_mb}MB available, ${NX_MIN_RAM_MB}MB recommended. Installation may fail on low-memory systems."
    ram_ok='warn'
  else
    nx_ok "RAM: ${ram_mb}MB available"
  fi

  if [[ "$disk_gb" -lt "$NX_MIN_DISK_GB" ]]; then
    nx_error "Disk: ${disk_gb}GB free, minimum ${NX_MIN_DISK_GB}GB required."
    disk_ok='no'
  else
    nx_ok "Disk: ${disk_gb}GB free on /"
  fi

  if [[ "$disk_ok" == 'no' ]]; then
    nx_fatal "Insufficient disk space. Free at least ${NX_MIN_DISK_GB}GB on / and re-run."
  fi
}

# ── Privilege checks ──────────────────────────────────────────────────────────

nx_check_network() {
  nx_info "Checking network connectivity…"
  if ! curl --silent --max-time 10 --head https://packages.sury.org/ > /dev/null 2>&1 &&
     ! curl --silent --max-time 10 --head https://packages.microsoft.com/ > /dev/null 2>&1; then
    nx_warn "Could not reach external package repositories. Ensure outbound HTTPS (port 443) is allowed."
  else
    nx_ok "Network: outbound HTTPS reachable"
  fi
}

# ── Security framework detection ──────────────────────────────────────────────

nx_detect_security() {
  local selinux='' apparmor=''

  if command -v getenforce &>/dev/null; then
    selinux="$(getenforce 2>/dev/null || echo 'unknown')"
    if [[ "$selinux" == 'Enforcing' ]]; then
      nx_info "SELinux is Enforcing. The installer will configure necessary contexts — not disable SELinux."
    fi
  fi

  if [[ -d /sys/kernel/security/apparmor ]]; then
    apparmor='active'
    nx_info "AppArmor is active. Required profiles will be installed."
  fi

  if [[ -z "$selinux" && -z "$apparmor" ]]; then
    nx_info "No mandatory access control framework detected."
  fi
}

# ── Conflict detection ─────────────────────────────────────────────────────────

nx_check_conflicts() {
  local conflicts=()

  for svc in nginx apache2 httpd; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
      conflicts+=("$svc is currently running (port 80/443 may be in use)")
    fi
  done

  for port in 80 443 5432 6379 8021 5060; do
    if ss -ltnp 2>/dev/null | grep -q ":${port}\b"; then
      nx_warn "Port $port is already in use. Verify no conflicting service is bound to it."
    fi
  done

  if [[ ${#conflicts[@]} -gt 0 ]]; then
    for c in "${conflicts[@]}"; do
      nx_warn "$c"
    done
    nx_warn "Existing web server detected. The installer will reconfigure it safely."
  fi
}

# ── Print summary ──────────────────────────────────────────────────────────────

nx_print_detection_summary() {
  nx_section "System Detection"
  nx_info "OS:           $NX_OS_ID $NX_OS_VERSION ($NX_OS_CODENAME)"
  nx_info "Architecture: $NX_ARCH"
  nx_info "Package mgr:  $NX_PKG_MGR"
  nx_info "Support:      $NX_SUPPORTED"
}
