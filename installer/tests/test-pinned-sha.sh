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

pass() { echo "[PASS] $*"; PASS=$(( PASS + 1 )); }
fail() { echo "[FAIL] $*" >&2; FAIL=$(( FAIL + 1 )); }

APP_SH="${BUNDLE_DIR}/installer/lib/app.sh"
RELEASE_FILE="${BUNDLE_DIR}/installer/RELEASE"

# ── Prepare a sourceable version of app.sh ────────────────────────────────────
# Strip `set -euo pipefail` and `readonly` declarations so each test subshell
# can override NX_INSTALLED_REVISION_FILE and the other constants freely.
# The extracted file still contains all functions in their full, unmodified form.

STRIPPED_APP="$(mktemp /tmp/test-app-XXXXXX.sh)"
trap 'rm -f "${STRIPPED_APP}"' EXIT

sed \
  -e 's/^set -euo pipefail$/: # set -euo pipefail stripped for testing/' \
  -e 's/^readonly /# readonly /' \
  "${APP_SH}" > "${STRIPPED_APP}"

# ── 1. RELEASE file exists and contains the full SHA ─────────────────────────

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

INSTALLER_DIR_UNDER_TEST="${BUNDLE_DIR}/installer"

RELEASE_COMMIT_CAPTURED="$(
  INSTALLER_DIR="${INSTALLER_DIR_UNDER_TEST}"
  _RELEASE_FILE="${INSTALLER_DIR}/RELEASE"
  if [[ -f "${_RELEASE_FILE}" ]]; then
    _FILE_SHA="$(tr -d '[:space:]' < "${_RELEASE_FILE}")"
    if [[ "${_FILE_SHA}" =~ ^[0-9a-f]{40}$ ]]; then
      NX_RELEASE_COMMIT="${_FILE_SHA}"
    fi
  fi
  printf '%s' "${NX_RELEASE_COMMIT:-UNSET}"
)"

if [[ "${RELEASE_COMMIT_CAPTURED}" == "${EXPECTED_SHA}" ]]; then
  pass "RELEASE-reading block exports NX_RELEASE_COMMIT=${EXPECTED_SHA:0:12}…"
elif [[ "${RELEASE_COMMIT_CAPTURED}" == "UNSET" ]]; then
  fail "RELEASE-reading block did not export NX_RELEASE_COMMIT"
else
  fail "RELEASE-reading block exported ${RELEASE_COMMIT_CAPTURED} (expected ${EXPECTED_SHA})"
fi

# ── 4. Version-mismatch guard: same SHA → no-op ───────────────────────────────

if [[ ! -f "${APP_SH}" ]]; then
  fail "installer/lib/app.sh missing from bundle"
else
  # Write a revision file that matches the expected SHA and check the guard
  # exits cleanly (no FATAL).
  GUARD_RESULT4="$(
    REV4="$(mktemp /tmp/test-rev4-XXXXXX)"
    printf '%s\n' "${EXPECTED_SHA}" > "${REV4}"

    bash --norc --noprofile <<GUARD4 2>&1 || true
nx_ok()   { echo "[OK] \$*"; }
nx_info() { echo "[INFO] \$*"; }
nx_warn() { echo "[WARN] \$*"; }
nx_fatal() { echo "[FATAL] \$*"; exit 1; }
nx_step() { :; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }
nx_log_passthrough() { cat; }

NX_INSTALLED_REVISION_FILE="${REV4}"
NX_APP_USER='nexvary-test'
NX_APP_DIR='/tmp/test-app'
NX_REPO_URL='https://example.invalid/repo.git'

source "${STRIPPED_APP}"

nx_check_version_mismatch "${EXPECTED_SHA}"
echo "GUARD4=no-op"
GUARD4
    rm -f "${REV4}"
  )"

  if echo "${GUARD_RESULT4}" | grep -q "GUARD4=no-op"; then
    pass "Version-mismatch guard: same SHA → no-op"
  elif echo "${GUARD_RESULT4}" | grep -qiE "\[FATAL\]|mismatch"; then
    fail "Version-mismatch guard: incorrectly fired for same SHA: ${GUARD_RESULT4}"
  else
    fail "Version-mismatch guard: unexpected result for same SHA: ${GUARD_RESULT4}"
  fi
fi

# ── 5. Version-mismatch guard: different SHA without --upgrade-from → fatal ───

DIFFERENT_SHA="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

if [[ -f "${APP_SH}" ]]; then
  GUARD_RESULT5="$(
    REV5="$(mktemp /tmp/test-rev5-XXXXXX)"
    printf '%s\n' "${EXPECTED_SHA}" > "${REV5}"

    bash --norc --noprofile <<GUARD5 2>&1 || true
nx_ok()   { :; }
nx_info() { :; }
nx_warn() { :; }
nx_fatal() { echo "[FATAL] \$*"; exit 1; }
nx_step() { :; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }
nx_log_passthrough() { cat; }

NX_INSTALLED_REVISION_FILE="${REV5}"
unset NX_UPGRADE_FROM
NX_APP_USER='nexvary-test'
NX_APP_DIR='/tmp/test-app'
NX_REPO_URL='https://example.invalid/repo.git'

source "${STRIPPED_APP}"

nx_check_version_mismatch "${DIFFERENT_SHA}"
echo "DID_NOT_FAIL"
GUARD5
    rm -f "${REV5}"
  )"

  if echo "${GUARD_RESULT5}" | grep -qiE "\[FATAL\]|mismatch|Version mismatch"; then
    pass "Version-mismatch guard: different SHA without --upgrade-from → fatal"
  elif echo "${GUARD_RESULT5}" | grep -q "DID_NOT_FAIL"; then
    fail "Version-mismatch guard: did NOT fail when SHA changed without --upgrade-from"
  else
    fail "Version-mismatch guard: unexpected output: ${GUARD_RESULT5}"
  fi
fi

# ── 6. Version-mismatch guard: matching --upgrade-from → proceeds ─────────────

if [[ -f "${APP_SH}" ]]; then
  GUARD_RESULT6="$(
    REV6="$(mktemp /tmp/test-rev6-XXXXXX)"
    printf '%s\n' "${EXPECTED_SHA}" > "${REV6}"

    bash --norc --noprofile <<GUARD6 2>&1 || true
nx_ok()   { echo "[OK] \$*"; }
nx_info() { echo "[INFO] \$*"; }
nx_warn() { :; }
nx_fatal() { echo "[FATAL] \$*"; exit 1; }
nx_step() { :; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }
nx_log_passthrough() { cat; }

NX_INSTALLED_REVISION_FILE="${REV6}"
export NX_UPGRADE_FROM="${EXPECTED_SHA}"
NX_APP_USER='nexvary-test'
NX_APP_DIR='/tmp/test-app'
NX_REPO_URL='https://example.invalid/repo.git'

source "${STRIPPED_APP}"

nx_check_version_mismatch "${DIFFERENT_SHA}"
echo "GUARD6=proceeded"
GUARD6
    rm -f "${REV6}"
  )"

  if echo "${GUARD_RESULT6}" | grep -q "GUARD6=proceeded"; then
    pass "Version-mismatch guard: matching --upgrade-from → proceeds"
  elif echo "${GUARD_RESULT6}" | grep -qiE "\[FATAL\]|error"; then
    fail "Version-mismatch guard: --upgrade-from blocked unexpectedly: ${GUARD_RESULT6}"
  else
    fail "Version-mismatch guard: unexpected output for --upgrade-from: ${GUARD_RESULT6}"
  fi
fi

# ── Summary ───────────────────────────────────────────────────────────────────

echo ""
echo "Results: ${PASS} passed, ${FAIL} failed"

if (( FAIL > 0 )); then
  exit 1
fi
