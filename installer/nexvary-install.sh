#!/usr/bin/env bash
# NEXVARY CX Cloud — Main Installer
# Step 2 of 2 in the two-command installation process.
#
# Usage:
#   sudo nexvary install [OPTIONS]
#
# Options:
#   --domain=FQDN          Domain name (e.g. pbx.example.com)
#   --https=certbot|self   Enable HTTPS (certbot requires public domain)
#   --certbot-email=EMAIL  Email for Let's Encrypt registration
#   --sw-token=TOKEN       SignalWire FOSS token for FreeSWITCH
#   --skip-freeswitch      Do not install FreeSWITCH (configure later)
#   --verbose              Show full package installation output
#   --yes                  Non-interactive (accept all prompts)
#   --ref=BRANCH           Git branch/tag to install (default: master)
#
# Environment variables (alternative to flags):
#   NX_DOMAIN, NX_HTTPS, NX_CERTBOT_EMAIL, NX_SW_TOKEN,
#   NX_FS_SKIP, NX_VERBOSE, NX_YES, NX_INSTALL_REF

set -euo pipefail

INSTALLER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALLER_LIB="${INSTALLER_DIR}/lib"
INSTALLER_LOG="/var/log/nexvary-install-$(date +%Y%m%d-%H%M%S).log"
NX_APP_DIR="${NX_APP_DIR:-/var/www/nexvary-cx}"
NX_APP_USER="${NX_APP_USER:-nexvary}"

# ── Load pinned SHA from bundle RELEASE file ──────────────────────────────────
# When installed from a versioned bundle, INSTALLER_DIR contains a RELEASE file
# written by the CI pipeline at bundle time.  Its content is the full 40-hex
# commit SHA that the bundle was built from.  This takes effect before argument
# parsing so --ref= can still override it for development installs.
_RELEASE_FILE="${INSTALLER_DIR}/RELEASE"
if [[ -f "${_RELEASE_FILE}" ]]; then
  _FILE_SHA="$(tr -d '[:space:]' < "${_RELEASE_FILE}")"
  if [[ "${_FILE_SHA}" =~ ^[0-9a-f]{40}$ ]]; then
    export NX_RELEASE_COMMIT="${_FILE_SHA}"
    printf '[INFO] Bundle release commit: %s\n' "${NX_RELEASE_COMMIT}"
  else
    printf '[WARN] RELEASE file exists but does not contain a valid SHA (got: %s) — ignoring\n' \
      "${_FILE_SHA}" >&2
  fi
fi
unset _RELEASE_FILE _FILE_SHA

# ── Parse arguments ───────────────────────────────────────────────────────────
for arg in "$@"; do
  case "$arg" in
    --domain=*)        export NX_DOMAIN="${arg#*=}" ;;
    --https=*)         export NX_HTTPS="${arg#*=}" ;;
    --certbot-email=*) export NX_CERTBOT_EMAIL="${arg#*=}" ;;
    --sw-token=*)      export NX_SW_TOKEN="${arg#*=}" ;;
    --skip-freeswitch) export NX_FS_SKIP='1' ;;
    --verbose)         export NX_VERBOSE='1' ;;
    --yes)             export NX_YES='1' ;;
    --ref=*)           export NX_INSTALL_REF="${arg#*=}" ;;
    --upgrade-from=*)  export NX_UPGRADE_FROM="${arg#*=}" ;;
    --reuse-cert)      export NX_HTTPS='existing-cert' ;;
    --help|-h)
      printf "Usage: sudo nexvary install [OPTIONS]\n"
      printf "See https://nexvary.com/docs/install for full documentation.\n"
      exit 0
      ;;
    *)
      printf "Unknown option: %s\n" "$arg" >&2
      exit 1
      ;;
  esac
done

# ── Load modules ──────────────────────────────────────────────────────────────
# shellcheck source=installer/lib/ui.sh
source "${INSTALLER_LIB}/ui.sh"
# shellcheck source=installer/lib/detect.sh
source "${INSTALLER_LIB}/detect.sh"

nx_require_root

# Redirect all output to log file as well
exec > >(tee -a "$INSTALLER_LOG") 2>&1
printf "NEXVARY CX Cloud installer — %s\n" "$(date -Iseconds)"

nx_banner

# ── Phase 1: Detection ────────────────────────────────────────────────────────

nx_section "Phase 1 — System Detection"
nx_detect_os

if [[ "$NX_SUPPORTED" == 'no' ]]; then
  nx_fatal "This operating system is not supported. See https://nexvary.com/docs/requirements"
fi

nx_print_detection_summary
nx_check_hardware
nx_detect_security
nx_check_conflicts

if [[ "$NX_SUPPORTED" == 'partial' ]]; then
  nx_warn "This OS version is not explicitly tested. Installation may succeed but is not guaranteed."
  if ! nx_confirm "Continue anyway?"; then
    nx_fatal "Installation cancelled."
  fi
fi

# ── Phase 2: Dependencies ─────────────────────────────────────────────────────

nx_section "Phase 2 — Installing Dependencies"

case "$NX_PKG_MGR" in
  apt)
    # shellcheck source=installer/lib/debian.sh
    source "${INSTALLER_LIB}/debian.sh"
    nx_apt_update
    nx_install_php_debian
    nx_install_postgresql_debian
    nx_install_redis_debian
    nx_install_nginx_debian
    nx_install_node_debian
    nx_install_composer_debian
    if [[ "${NX_HTTPS:-}" == 'certbot' ]]; then
      nx_install_certbot_debian
    fi
    ;;

  dnf)
    # shellcheck source=installer/lib/centos.sh
    source "${INSTALLER_LIB}/centos.sh"
    nx_dnf_update
    nx_install_php_centos
    nx_install_postgresql_centos
    nx_install_redis_centos
    nx_install_nginx_centos
    nx_install_node_centos
    nx_install_composer_centos
    nx_configure_selinux_centos
    nx_configure_firewall_centos
    if [[ "${NX_HTTPS:-}" == 'certbot' ]]; then
      nx_install_certbot_centos
    fi
    ;;

  *)
    nx_fatal "No supported package manager found (apt or dnf required)."
    ;;
esac

# ── Phase 3: FreeSWITCH ───────────────────────────────────────────────────────

nx_section "Phase 3 — FreeSWITCH"

if [[ "${NX_FS_SKIP:-}" == '1' ]]; then
  nx_warn "FreeSWITCH installation skipped (--skip-freeswitch)"
  nx_warn "Configure FreeSWITCH manually and re-run: nexvary configure-freeswitch"
else
  # shellcheck source=installer/lib/freeswitch.sh
  source "${INSTALLER_LIB}/freeswitch.sh"
  case "$NX_PKG_MGR" in
    apt) nx_install_freeswitch_debian ;;
    dnf) nx_install_freeswitch_centos ;;
  esac
fi

# ── Phase 4: Application Deployment ──────────────────────────────────────────

nx_section "Phase 4 — Application Deployment"

# shellcheck source=installer/lib/app.sh
source "${INSTALLER_LIB}/app.sh"

nx_create_app_user
nx_deploy_app
nx_composer_install
nx_build_frontend
nx_generate_env
nx_configure_postgresql
nx_configure_redis
nx_set_permissions

# Wait for PostgreSQL to accept connections before running migrations.
# pg_isready is provided by the postgresql-client package (installed with PostgreSQL).
_pg_wait=0
until pg_isready -q 2>/dev/null; do
  _pg_wait=$((_pg_wait + 1))
  if [[ $_pg_wait -ge 30 ]]; then
    nx_fatal "PostgreSQL did not become ready within 30 seconds — check: journalctl -xe -u postgresql*"
  fi
  sleep 1
done
unset _pg_wait

nx_run_migrations
nx_cache_app

# ── Phase 5: Web Server ───────────────────────────────────────────────────────

nx_section "Phase 5 — Web Server"

# shellcheck source=installer/lib/nginx.sh
source "${INSTALLER_LIB}/nginx.sh"

nx_configure_php_fpm

# Auto-detect an existing Let's Encrypt certificate so the owner does not
# need a flag when the host already has HTTPS configured.
if [[ -z "${NX_HTTPS:-}" && -n "${NX_DOMAIN:-}" ]]; then
  if [[ -f "/etc/letsencrypt/live/${NX_DOMAIN}/fullchain.pem" ]]; then
    export NX_HTTPS='existing-cert'
    nx_info "Existing certificate detected for ${NX_DOMAIN} — reusing (pass --https=certbot to override)."
  fi
fi

case "${NX_HTTPS:-}" in
  existing-cert) nx_configure_nginx_https ;;
  certbot)       nx_configure_nginx; nx_configure_https_certbot ;;
  selfsigned)    nx_configure_nginx; nx_configure_https_selfsigned ;;
  *)             nx_configure_nginx ;;
esac

# ── Phase 6: systemd Services ─────────────────────────────────────────────────

nx_section "Phase 6 — Configuring Services"

# shellcheck source=installer/lib/systemd.sh
source "${INSTALLER_LIB}/systemd.sh"

nx_install_queue_worker
nx_install_scheduler
nx_verify_services

# ── Phase 7: Final Checks ─────────────────────────────────────────────────────

nx_section "Phase 7 — Final Verification"

if [[ "${NX_FS_SKIP:-}" != '1' ]]; then
  nx_verify_freeswitch
fi

nx_print_setup_url

nx_info "Full installation log: $INSTALLER_LOG"
nx_done "NEXVARY CX Cloud installation complete!"
