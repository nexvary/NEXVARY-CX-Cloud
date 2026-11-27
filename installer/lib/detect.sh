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

    centos|rhel|rocky|almalinux|fedora)
      # RHEL-family distributions are not supported.
      # Owner decision (Stage 2): NEXVARY CX Cloud supports Debian 12/13 and
      # Ubuntu 24.04 LTS only. CentOS and all RHEL-based variants are excluded
      # from the current release.
      NX_SUPPORTED='no'
      nx_error "NEXVARY CX Cloud does not support $NX_OS_ID $NX_OS_VERSION."
      nx_error "Supported platforms: Debian 12, Debian 13, Ubuntu 24.04 LTS."
      return 1
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

# ── Existing installation detection ──────────────────────────────────────────
# Outputs:
#   NX_EXISTING_INSTALL  'yes'|'no'
#   NX_EXISTING_VERSION  commit SHA of installed version (if readable)
#   NX_EXISTING_APP_DIR  path to detected installation

NX_EXISTING_INSTALL='no'
NX_EXISTING_VERSION=''
NX_EXISTING_APP_DIR=''

nx_detect_existing_install() {
  local candidate_dirs=('/var/www/nexvary-cx' '/opt/nexvary-cx' "${NX_APP_DIR:-}")
  local found_dir=''

  for d in "${candidate_dirs[@]}"; do
    [[ -z "$d" ]] && continue
    if [[ -f "${d}/artisan" && -f "${d}/.env" ]]; then
      found_dir="$d"
      break
    fi
  done

  if [[ -z "$found_dir" ]]; then
    NX_EXISTING_INSTALL='no'
    return 0
  fi

  NX_EXISTING_INSTALL='yes'
  NX_EXISTING_APP_DIR="$found_dir"

  # Attempt to read the pinned commit SHA written at deploy time
  local version_file="${found_dir}/.nexvary-version"
  if [[ -f "$version_file" ]]; then
    NX_EXISTING_VERSION="$(tr -d '[:space:]' < "$version_file")"
  fi

  # Fallback: read from git if the app dir is a git working tree
  if [[ -z "$NX_EXISTING_VERSION" && -d "${found_dir}/.git" ]]; then
    NX_EXISTING_VERSION="$(git -C "$found_dir" rev-parse HEAD 2>/dev/null || true)"
  fi

  nx_info "Existing NEXVARY CX Cloud installation detected: ${found_dir}"
  if [[ -n "$NX_EXISTING_VERSION" ]]; then
    nx_info "Installed version: ${NX_EXISTING_VERSION}"
  fi
}

nx_check_upgrade_safety() {
  # Called when NX_EXISTING_INSTALL=yes.
  # If --upgrade-from= was not passed, ask interactively.
  if [[ "${NX_UPGRADE_FROM:-}" == '' ]]; then
    if [[ "${NX_YES:-}" == '1' ]]; then
      nx_warn "--upgrade-from not set; proceeding with upgrade (--yes mode)."
      return 0
    fi
    nx_warn "An existing installation was detected."
    if ! nx_confirm "Upgrade the existing installation?"; then
      nx_fatal "Installation cancelled. Run with --upgrade-from=<sha> to confirm."
    fi
  fi

  # Version mismatch guard
  if [[ -n "$NX_EXISTING_VERSION" && -n "${NX_RELEASE_COMMIT:-}" ]]; then
    if [[ "$NX_EXISTING_VERSION" == "$NX_RELEASE_COMMIT" ]]; then
      nx_warn "Installed version matches this bundle — reinstalling the same version."
    fi
  fi
}

nx_backup_config_before_upgrade() {
  # Create a timestamped backup of key config files before an upgrade.
  # Backup is written to /var/backups/nexvary/pre-upgrade-<timestamp>/.
  local ts
  ts="$(date +%Y%m%d-%H%M%S)"
  local backup_dir="/var/backups/nexvary/pre-upgrade-${ts}"
  mkdir -p "$backup_dir"

  local app_dir="${NX_EXISTING_APP_DIR:-/var/www/nexvary-cx}"

  for f in '.env' 'storage/app/freeswitch-conf' 'storage/app/backups'; do
    local src="${app_dir}/${f}"
    if [[ -e "$src" ]]; then
      cp -a "$src" "${backup_dir}/" 2>/dev/null || true
    fi
  done

  # Back up FreeSWITCH config dir if present
  if [[ -d /etc/freeswitch ]]; then
    tar -czf "${backup_dir}/freeswitch-conf.tar.gz" /etc/freeswitch 2>/dev/null || true
  fi

  nx_ok "Pre-upgrade backup written to: ${backup_dir}"
  printf '%s\n' "$backup_dir"
}

nx_rollback_config() {
  # Restore the most recent pre-upgrade backup.
  # Usage: nx_rollback_config [backup_dir]
  local backup_dir="${1:-}"

  if [[ -z "$backup_dir" ]]; then
    # Find the most recent backup
    backup_dir="$(find /var/backups/nexvary -maxdepth 1 -name 'pre-upgrade-*' -type d 2>/dev/null | sort | tail -1)"
  fi

  if [[ -z "$backup_dir" || ! -d "$backup_dir" ]]; then
    nx_error "No backup directory found for rollback."
    return 1
  fi

  nx_warn "Rolling back configuration from: ${backup_dir}"

  local app_dir="${NX_EXISTING_APP_DIR:-/var/www/nexvary-cx}"

  if [[ -f "${backup_dir}/.env" ]]; then
    cp "${backup_dir}/.env" "${app_dir}/.env"
    nx_ok "Restored .env"
  fi

  if [[ -f "${backup_dir}/freeswitch-conf.tar.gz" ]]; then
    tar -xzf "${backup_dir}/freeswitch-conf.tar.gz" -C / 2>/dev/null || true
    nx_ok "Restored FreeSWITCH config"
  fi

  nx_ok "Rollback complete. Review the application state before restarting services."
}
