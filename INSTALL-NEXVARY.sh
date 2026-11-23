#!/usr/bin/env bash
# NEXVARY CX Cloud — Installation Entry Point
#
# This is the bundle's single entry point.  It reads the pinned commit SHA from
# installer/RELEASE and delegates all installation work to
# installer/nexvary-install.sh.  Every command-line option is forwarded
# unchanged, so this script and the modular installer are interchangeable.
#
# Usage (from the extracted bundle directory):
#   sudo bash INSTALL-NEXVARY.sh [OPTIONS]
#
# Common options:
#   --domain=FQDN          Server hostname (required for HTTPS / QR pairing)
#   --https=certbot        Request a new Let's Encrypt certificate
#   --certbot-email=EMAIL  Email for Let's Encrypt
#   --skip-freeswitch      Skip FreeSWITCH (configure later)
#   --yes                  Non-interactive (accept all prompts)
#   --verbose              Show full package output
#
# SHA pinning:
#   installer/RELEASE contains the exact 40-hex commit this bundle was built
#   from.  The installer reads it and refuses to deploy any other commit.
#   Pass --upgrade-from=<installed-sha> when replacing an existing install.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALLER_SCRIPT="${SCRIPT_DIR}/installer/nexvary-install.sh"
RELEASE_FILE="${SCRIPT_DIR}/installer/RELEASE"

# ── Sanity checks ─────────────────────────────────────────────────────────────

if [[ ! -f "${INSTALLER_SCRIPT}" ]]; then
  printf '[ERROR] installer/nexvary-install.sh not found.\n' >&2
  printf '        Run this script from the extracted bundle directory:\n' >&2
  printf '          tar -xzf nexvary-cx-*.tar.gz\n' >&2
  printf '          cd nexvary-cx-*/\n' >&2
  printf '          sudo bash INSTALL-NEXVARY.sh\n' >&2
  exit 1
fi

if [[ $EUID -ne 0 ]]; then
  printf '[ERROR] This installer must be run as root.\n' >&2
  printf '        Try: sudo bash INSTALL-NEXVARY.sh\n' >&2
  exit 1
fi

# ── Read pinned SHA from RELEASE file ─────────────────────────────────────────
# The RELEASE file is written by the CI pipeline at bundle time.
# When NX_RELEASE_COMMIT is already set by the caller, preserve it.

if [[ -z "${NX_RELEASE_COMMIT:-}" && -f "${RELEASE_FILE}" ]]; then
  _file_sha="$(tr -d '[:space:]' < "${RELEASE_FILE}")"
  if [[ "${_file_sha}" =~ ^[0-9a-f]{40}$ ]]; then
    export NX_RELEASE_COMMIT="${_file_sha}"
    printf '[INFO] Bundle release commit: %s\n' "${NX_RELEASE_COMMIT}"
  else
    printf '[WARN] installer/RELEASE does not contain a valid SHA (got: %s) — ignoring\n' \
      "${_file_sha}" >&2
  fi
  unset _file_sha
fi

# ── Delegate to modular installer ─────────────────────────────────────────────

exec bash "${INSTALLER_SCRIPT}" "$@"
