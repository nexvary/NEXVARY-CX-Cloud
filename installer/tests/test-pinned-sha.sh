#!/usr/bin/env bash
# Verify that the installer correctly pins to the SHA recorded in the bundle's
# RELEASE file, and that the version-mismatch guard fires on a conflicting
# existing installation.
#
# Usage (called by release-bundle.yml verify job):
#   bash installer/tests/test-pinned-sha.sh <bundle_dir> <expected_sha>
#
# Exit code: 0 = all checks passed, non-zero = at least one failure.

set -euo pipefail

BUNDLE_DIR="${1:?Usage: test-pinned-sha.sh <bundle_dir> <expected_sha>}"
EXPECTED_SHA="${2:?Usage: test-pinned-sha.sh <bundle_dir> <expected_sha>}"

PASS=0
FAIL=0

pass() { echo "[PASS] $*"; (( PASS++ )) || true; }
fail() { echo "[FAIL] $*" >&2; (( FAIL++ )) || true; }

# ── 1. RELEASE file exists and contains the full SHA ─────────────────────────

RELEASE_FILE="${BUNDLE_DIR}/installer/RELEASE"

if [[ ! -f "${RELEASE_FILE}" ]]; then
  fail "RELEASE file missing from bundle: ${RELEASE_FILE}"
else
  ACTUAL_SHA="$(tr -d '[:space:]' < "${RELEASE_FILE}")"
  if [[ "${ACTUAL_SHA}" == "${EXPECTED_SHA}" ]]; then
    pass "RELEASE file contains expected SHA: ${EXPECTED_SHA}"
  else
    fail "RELEASE file SHA mismatch: expected=${EXPECTED_SHA} actual=${ACTUAL_SHA}"
  fi
fi

# ── 2. RELEASE file SHA is a valid 40-hex string ─────────────────────────────

if [[ -f "${RELEASE_FILE}" ]]; then
  ACTUAL_SHA="$(tr -d '[:space:]' < "${RELEASE_FILE}")"
  if [[ "${ACTUAL_SHA}" =~ ^[0-9a-f]{40}$ ]]; then
    pass "RELEASE SHA is a valid 40-character hex string"
  else
    fail "RELEASE SHA '${ACTUAL_SHA}' is not a valid 40-character hex SHA"
  fi
fi

# ── 3. nexvary-install.sh reads RELEASE and exports NX_RELEASE_COMMIT ─────────
#
# Stub the shell functions that nexvary-install.sh sources so we can source
# the argument-parsing section without executing the actual installation.

# Create a minimal stub lib that satisfies the source calls
STUB_DIR="$(mktemp -d)"
trap 'rm -rf "${STUB_DIR}"' EXIT

for lib_file in ui.sh detect.sh; do
  cat > "${STUB_DIR}/${lib_file}" <<'STUB'
nx_require_root()   { :; }
nx_banner()         { :; }
nx_section()        { :; }
nx_detect_os()      { :; }
nx_print_detection_summary() { :; }
nx_check_hardware() { :; }
nx_detect_security(){ :; }
nx_check_conflicts(){ :; }
nx_fatal()          { echo "[FATAL] $*" >&2; exit 1; }
nx_warn()           { echo "[WARN]  $*"; }
nx_info()           { echo "[INFO]  $*"; }
nx_ok()             { echo "[OK]    $*"; }
NX_SUPPORTED='yes'
NX_PKG_MGR='apt'
STUB
done

# Source only the RELEASE-reading and argument-parsing sections.
# We do this by running a subshell that sets INSTALLER_DIR to the bundle's
# installer directory, sources the stub lib, then runs the RELEASE-loading
# block extracted from nexvary-install.sh.
INSTALLER_DIR_UNDER_TEST="${BUNDLE_DIR}/installer"

RELEASE_COMMIT_CAPTURED="$(bash -euo pipefail <<SUBSHELL
INSTALLER_DIR="${INSTALLER_DIR_UNDER_TEST}"
_RELEASE_FILE="\${INSTALLER_DIR}/RELEASE"
if [[ -f "\${_RELEASE_FILE}" ]]; then
  _FILE_SHA="\$(tr -d '[:space:]' < "\${_RELEASE_FILE}")"
  if [[ "\${_FILE_SHA}" =~ ^[0-9a-f]{40}$ ]]; then
    export NX_RELEASE_COMMIT="\${_FILE_SHA}"
  fi
fi
printf '%s' "\${NX_RELEASE_COMMIT:-UNSET}"
SUBSHELL
)"

if [[ "${RELEASE_COMMIT_CAPTURED}" == "${EXPECTED_SHA}" ]]; then
  pass "nexvary-install.sh RELEASE-reading block exports NX_RELEASE_COMMIT=${EXPECTED_SHA:0:12}…"
elif [[ "${RELEASE_COMMIT_CAPTURED}" == "UNSET" ]]; then
  fail "nexvary-install.sh did not export NX_RELEASE_COMMIT from the RELEASE file"
else
  fail "nexvary-install.sh exported NX_RELEASE_COMMIT=${RELEASE_COMMIT_CAPTURED} (expected ${EXPECTED_SHA})"
fi

# ── 4. Version-mismatch guard: same SHA → no-op ───────────────────────────────
#
# Source app.sh with stubs and verify check_version_mismatch does NOT exit
# when installed_sha == target_sha.

APP_SH="${BUNDLE_DIR}/installer/lib/app.sh"

if [[ ! -f "${APP_SH}" ]]; then
  fail "installer/lib/app.sh missing from bundle"
else
  MISMATCH_RESULT="$(bash -euo pipefail <<SUBSHELL 2>&1 || true
# Provide minimal stubs
nx_ok()   { echo "[OK] \$*"; }
nx_info() { echo "[INFO] \$*"; }
nx_warn() { echo "[WARN] \$*"; }
nx_step() { :; }
nx_fatal() { echo "[FATAL] \$*"; exit 1; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }
nx_log_passthrough() { cat; }

# Override the revision-file path to a temp file we control
NX_INSTALLED_REVISION_FILE="\$(mktemp)"
printf '%s\n' '${EXPECTED_SHA}' > "\${NX_INSTALLED_REVISION_FILE}"

# Source only the guard function (not the full lib which has set -euo pipefail)
$(grep -A 50 'nx_check_version_mismatch()' "${APP_SH}" | head -55)

export NX_RELEASE_COMMIT='${EXPECTED_SHA}'
nx_check_version_mismatch '${EXPECTED_SHA}'
echo "GUARD_RESULT=no-op"
SUBSHELL
)"
  if echo "${MISMATCH_RESULT}" | grep -q "GUARD_RESULT=no-op"; then
    pass "Version-mismatch guard: same SHA → no-op (already up-to-date)"
  elif echo "${MISMATCH_RESULT}" | grep -q "FATAL\|mismatch"; then
    fail "Version-mismatch guard incorrectly fired for same SHA: ${MISMATCH_RESULT}"
  else
    fail "Version-mismatch guard: unexpected result: ${MISMATCH_RESULT}"
  fi
fi

# ── 5. Version-mismatch guard: different SHA → fatal ─────────────────────────

DIFFERENT_SHA="$(printf '%040d' 0 | tr '0' 'a')"  # 40 × 'a'

if [[ -f "${APP_SH}" ]]; then
  MISMATCH_RESULT2="$(bash <<SUBSHELL 2>&1 || true
nx_ok()   { :; }
nx_info() { :; }
nx_warn() { :; }
nx_step() { :; }
nx_fatal() { echo "[FATAL] \$*"; exit 1; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }
nx_log_passthrough() { cat; }

NX_INSTALLED_REVISION_FILE="\$(mktemp)"
printf '%s\n' '${EXPECTED_SHA}' > "\${NX_INSTALLED_REVISION_FILE}"
unset NX_UPGRADE_FROM

$(grep -A 50 'nx_check_version_mismatch()' "${APP_SH}" | head -55)

nx_check_version_mismatch '${DIFFERENT_SHA}'
echo "DID_NOT_FAIL"
SUBSHELL
)"
  if echo "${MISMATCH_RESULT2}" | grep -qiE "\[FATAL\]|mismatch|Version mismatch"; then
    pass "Version-mismatch guard: different SHA → fatal (as expected)"
  elif echo "${MISMATCH_RESULT2}" | grep -q "DID_NOT_FAIL"; then
    fail "Version-mismatch guard DID NOT fail when SHA changed without --upgrade-from"
  else
    fail "Version-mismatch guard: unexpected output: ${MISMATCH_RESULT2}"
  fi
fi

# ── 6. Version-mismatch guard: explicit --upgrade-from → proceeds ─────────────

if [[ -f "${APP_SH}" ]]; then
  UPGRADE_RESULT="$(bash <<SUBSHELL 2>&1 || true
nx_ok()   { echo "[OK] \$*"; }
nx_info() { echo "[INFO] \$*"; }
nx_warn() { :; }
nx_step() { :; }
nx_fatal() { echo "[FATAL] \$*"; exit 1; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }
nx_log_passthrough() { cat; }

NX_INSTALLED_REVISION_FILE="\$(mktemp)"
printf '%s\n' '${EXPECTED_SHA}' > "\${NX_INSTALLED_REVISION_FILE}"
export NX_UPGRADE_FROM='${EXPECTED_SHA}'

$(grep -A 50 'nx_check_version_mismatch()' "${APP_SH}" | head -55)

nx_check_version_mismatch '${DIFFERENT_SHA}'
echo "UPGRADE_PROCEEDED"
SUBSHELL
)"
  if echo "${UPGRADE_RESULT}" | grep -q "UPGRADE_PROCEEDED"; then
    pass "Version-mismatch guard: matching --upgrade-from → proceeds"
  else
    fail "Version-mismatch guard: --upgrade-from did not allow upgrade: ${UPGRADE_RESULT}"
  fi
fi

# ── Summary ───────────────────────────────────────────────────────────────────

echo ""
echo "Results: ${PASS} passed, ${FAIL} failed"

if (( FAIL > 0 )); then
  exit 1
fi
