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

# ── Phase 5: Web Server ───────────────────────────────────────────────────────

nx_section "Phase 5 — Web Server"

# shellcheck source=installer/lib/nginx.sh
source "${INSTALLER_LIB}/nginx.sh"

nx_configure_php_fpm
nx_configure_nginx

case "${NX_HTTPS:-}" in
  certbot)    nx_configure_https_certbot ;;
  selfsigned) nx_configure_https_selfsigned ;;
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

[[ "${NX_FS_SKIP:-}" != '1' ]] && nx_verify_freeswitch 2>/dev/null || true

nx_print_setup_url

nx_info "Full installation log: $INSTALLER_LOG"
nx_done "NEXVARY CX Cloud installation complete!"
