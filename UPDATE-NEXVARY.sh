#!/usr/bin/env bash
# NEXVARY CX Cloud — Upgrade Script
#
# Unpacks a versioned release bundle and runs its installer in upgrade mode.
# This script is a thin wrapper; the bundle's own installer handles all
# application changes (migrations, caches, service restarts).
#
# Usage:
#   sudo bash UPDATE-NEXVARY.sh \
#       --bundle=/path/to/nexvary-cx-*.tar.gz \
#       --domain=your.domain.com \
#       --upgrade-from=<currently-installed-sha>
#
# Find the currently installed SHA:
#   cat /etc/nexvary/installed_revision
#
# Both --bundle and --upgrade-from are required to prevent accidental upgrades.

set -euo pipefail

BUNDLE_PATH=''
DOMAIN_ARG=''
UPGRADE_FROM=''
EXTRA_ARGS=()

for _arg in "$@"; do
  case "$_arg" in
    --bundle=*)       BUNDLE_PATH="${_arg#*=}" ;;
    --domain=*)       DOMAIN_ARG="$_arg" ;;
    --upgrade-from=*) UPGRADE_FROM="${_arg#*=}" ;;
    --https=*|--certbot-email=*|--sw-token=*|--skip-freeswitch|--verbose|--yes)
      EXTRA_ARGS+=("$_arg") ;;
    --help|-h)
      printf 'Usage: sudo bash UPDATE-NEXVARY.sh --bundle=<tarball> --domain=<fqdn> --upgrade-from=<sha>\n'
      exit 0 ;;
    *)
      printf '[ERROR] Unknown option: %s\n' "$_arg" >&2
      exit 1 ;;
  esac
done
unset _arg

# ── Require root ──────────────────────────────────────────────────────────────
if [[ "$(id -u)" -ne 0 ]]; then
  printf '[ERROR] This script must be run as root.\n' >&2
  exit 1
fi

# ── Require --bundle ──────────────────────────────────────────────────────────
if [[ -z "$BUNDLE_PATH" ]]; then
  printf '[ERROR] --bundle=<tarball> is required.\n' >&2
  printf '  sudo bash UPDATE-NEXVARY.sh --bundle=/path/to/nexvary-cx-*.tar.gz --domain=... --upgrade-from=<sha>\n' >&2
  exit 1
fi

if [[ ! -f "$BUNDLE_PATH" ]]; then
  printf '[ERROR] Bundle file not found: %s\n' "$BUNDLE_PATH" >&2
  exit 1
fi

# ── Require --upgrade-from ────────────────────────────────────────────────────
if [[ -z "$UPGRADE_FROM" ]]; then
  printf '[ERROR] --upgrade-from=<sha> is required.\n' >&2
  _installed="$(cat /etc/nexvary/installed_revision 2>/dev/null | tr -d '[:space:]' || echo '')"
  if [[ -n "$_installed" ]]; then
    printf '  Currently installed: %s\n' "$_installed" >&2
    printf '  Add: --upgrade-from=%s\n' "$_installed" >&2
  fi
  unset _installed
  exit 1
fi

# ── Verify installed SHA matches --upgrade-from ───────────────────────────────
_installed="$(cat /etc/nexvary/installed_revision 2>/dev/null | tr -d '[:space:]' || echo '')"
if [[ -n "$_installed" && "$_installed" != "$UPGRADE_FROM" ]]; then
  printf '[ERROR] Version mismatch:\n' >&2
  printf '  Installed    : %s\n' "$_installed" >&2
  printf '  --upgrade-from: %s\n' "$UPGRADE_FROM" >&2
  printf '  Run: cat /etc/nexvary/installed_revision\n' >&2
  unset _installed
  exit 1
fi
unset _installed

# ── Read domain from existing .env if not provided ────────────────────────────
if [[ -z "$DOMAIN_ARG" && -f /var/www/nexvary-cx/.env ]]; then
  _env_domain="$(grep '^APP_URL=' /var/www/nexvary-cx/.env 2>/dev/null | \
    sed -E 's|^APP_URL=https?://||; s|/$||' | tr -d '[:space:]' || echo '')"
  if [[ -n "$_env_domain" && "$_env_domain" != 'localhost' ]]; then
    DOMAIN_ARG="--domain=${_env_domain}"
    printf '[INFO] Domain from existing .env: %s\n' "$_env_domain"
  fi
  unset _env_domain
fi

# ── Unpack bundle ─────────────────────────────────────────────────────────────
WORK_DIR="$(mktemp -d /tmp/nexvary-update-XXXXXX)"
trap 'rm -rf "$WORK_DIR"' EXIT

LOG="/var/log/nexvary-install-$(date +%Y%m%d-%H%M%S).log"
mkdir -p /var/log
exec > >(tee -a "$LOG") 2>&1

printf '[INFO] Unpacking bundle: %s\n' "$BUNDLE_PATH"
tar -xzf "$BUNDLE_PATH" -C "$WORK_DIR" --strip-components=1

INSTALLER="${WORK_DIR}/INSTALL-NEXVARY.sh"
if [[ ! -f "$INSTALLER" ]]; then
  printf '[ERROR] Bundle does not contain INSTALL-NEXVARY.sh\n' >&2
  exit 1
fi

# ── Build installer argument list ─────────────────────────────────────────────
INSTALLER_ARGS=()
[[ -n "$DOMAIN_ARG" ]] && INSTALLER_ARGS+=("$DOMAIN_ARG")
INSTALLER_ARGS+=("--upgrade-from=${UPGRADE_FROM}")
if [[ ${#EXTRA_ARGS[@]} -gt 0 ]]; then
  INSTALLER_ARGS+=("${EXTRA_ARGS[@]}")
fi

# ── Delegate to bundle installer ──────────────────────────────────────────────
printf '[INFO] Delegating to bundle installer\n'
exec bash "$INSTALLER" "${INSTALLER_ARGS[@]}"
