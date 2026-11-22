#!/usr/bin/env bash
# Installer detection unit test
# Usage: bash test-detect.sh <expected_id> <expected_version> <expected_pkg_mgr> <expected_supported>

set -euo pipefail

INSTALLER_LIB='/installer/lib'
EXPECTED_ID="${1:?}"
EXPECTED_VERSION="${2:?}"
EXPECTED_PKG_MGR="${3:?}"
EXPECTED_SUPPORTED="${4:?}"

# Minimal stubs so lib scripts don't error on missing dependencies
nx_warn() { echo "WARN: $*" >&2; }
nx_error() { echo "ERROR: $*" >&2; }
nx_fatal() { echo "FATAL: $*" >&2; exit 1; }
nx_ok()   { echo "OK: $*"; }
nx_info() { echo "INFO: $*"; }
nx_step() { echo "STEP: $*"; }
export -f nx_warn nx_error nx_fatal nx_ok nx_info nx_step

# Source detection module
# shellcheck source=../lib/detect.sh
source "${INSTALLER_LIB}/detect.sh"

echo "--- Running detection ---"
nx_detect_os
echo "NX_OS_ID=$NX_OS_ID"
echo "NX_OS_VERSION=$NX_OS_VERSION"
echo "NX_PKG_MGR=$NX_PKG_MGR"
echo "NX_SUPPORTED=$NX_SUPPORTED"
echo "NX_ARCH=$NX_ARCH"

PASS=true

check() {
  local name="$1" actual="$2" expected="$3"
  if [[ "$actual" == "$expected" ]]; then
    echo "  ✓ $name: '$actual'"
  else
    echo "  ✗ $name: expected '$expected', got '$actual'" >&2
    PASS=false
  fi
}

echo "--- Asserting values ---"
check "NX_OS_ID"       "$NX_OS_ID"       "$EXPECTED_ID"
check "NX_OS_VERSION"  "$NX_OS_VERSION"  "$EXPECTED_VERSION"
check "NX_PKG_MGR"     "$NX_PKG_MGR"     "$EXPECTED_PKG_MGR"
check "NX_SUPPORTED"   "$NX_SUPPORTED"   "$EXPECTED_SUPPORTED"

[[ -n "$NX_ARCH" ]] && echo "  ✓ NX_ARCH: '$NX_ARCH'" || { echo "  ✗ NX_ARCH is empty" >&2; PASS=false; }

echo "--- Hardware check (informational) ---"
nx_check_hardware || true

echo "--- Security detection (informational) ---"
nx_detect_security || true

if $PASS; then
  echo ""
  echo "PASS: All detection assertions passed."
  exit 0
else
  echo ""
  echo "FAIL: One or more assertions failed." >&2
  exit 1
fi
