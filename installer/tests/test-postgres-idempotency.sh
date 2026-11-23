#!/usr/bin/env bash
# PostgreSQL idempotency and credential-recovery test.
#
# Verifies that nx_configure_postgresql:
#   1. Creates user/database and stores credentials on a fresh system.
#   2. Preserves the same password on a second run (idempotency).
#   3. Does not write pg_shadow hash verifiers to database.conf or .env.
#   4. Recovers cleanly when the credentials file is lost (rotates password,
#      re-authenticates, leaves application data intact).
#
# Run inside a Debian 13 container:
#   docker run --rm --privileged \
#     -v "$PWD/installer:/installer:ro" \
#     debian:trixie \
#     bash /installer/tests/test-postgres-idempotency.sh
set -euo pipefail

INSTALLER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ── UI stubs — defined before sourcing lib scripts ────────────────────────────
# Neither debian.sh nor app.sh defines these; they come from ui.sh at runtime.
nx_step()          { printf '\n  ── %s\n' "$*"; }
nx_ok()            { printf '  [OK]   %s\n' "$*"; }
nx_info()          { printf '  [INF]  %s\n' "$*"; }
nx_warn()          { printf '  [WARN] %s\n' "$*"; }
nx_error()         { printf '  [ERR]  %s\n' "$*" >&2; }
nx_fatal()         { printf '  [FATAL] %s\n' "$*" >&2; exit 1; }
nx_spinner_start() { :; }
nx_spinner_stop()  { :; }

# Show all installation output so CI logs contain diagnostic detail.
export NX_VERBOSE=1

# ── Source production library scripts ────────────────────────────────────────
# Reuses production repository-setup and configuration logic without duplicating it.
# shellcheck source=installer/lib/debian.sh
source "${INSTALLER_DIR}/lib/debian.sh"
# shellcheck source=installer/lib/app.sh
source "${INSTALLER_DIR}/lib/app.sh"

# ── Bootstrap prerequisites ───────────────────────────────────────────────────
# sudo is required because nx_configure_postgresql calls `sudo -u postgres psql`.
# ca-certificates / curl / gnupg / lsb-release are needed by nx_add_sury_php
# (called transitively by nx_install_postgresql_debian's repo-key download).
export DEBIAN_FRONTEND=noninteractive

printf '[SETUP] Updating package lists...\n'
apt-get update

printf '[SETUP] Installing prerequisites...\n'
apt-get install -y sudo ca-certificates curl gnupg lsb-release openssl

# ── Install PostgreSQL 16 via the production PGDG procedure ──────────────────
# Using nx_install_postgresql_debian() avoids duplicating repository-setup
# logic and guarantees the test environment matches what the real installer
# creates on a customer host.
printf '[SETUP] Installing PostgreSQL 16 (production nx_install_postgresql_debian)...\n'
nx_install_postgresql_debian

if ! pg_isready -h 127.0.0.1 -p 5432 -q; then
  printf '[FAIL] PostgreSQL is not ready after nx_install_postgresql_debian\n' >&2
  exit 1
fi
printf '[SETUP] PostgreSQL 16 ready\n'

# ── Application skeleton ──────────────────────────────────────────────────────
mkdir -p /var/www/nexvary-cx /etc/nexvary

useradd --system --home-dir /var/www/nexvary-cx --no-create-home \
  --shell /sbin/nologin nexvary 2>/dev/null || true

# Minimal .env so the sed writes in nx_configure_postgresql have a file to update.
cat > /var/www/nexvary-cx/.env <<'ENV'
DB_PASSWORD=
DB_USERNAME=nexvary_cx
DB_DATABASE=nexvary_cx
ENV

# ── Helper ────────────────────────────────────────────────────────────────────
_read_stored_pass() {
  grep '^DB_PASSWORD=' /etc/nexvary/database.conf 2>/dev/null \
    | cut -d'=' -f2- | tr -d '[:space:]' || echo ''
}

# ════════════════════════════════════════════════════════════════════════════════
# TEST 1 — Fresh configuration
# ════════════════════════════════════════════════════════════════════════════════
printf '\n[TEST 1] Fresh configuration\n'
nx_configure_postgresql

pass1="$(_read_stored_pass)"
[[ -n "$pass1" ]] || { printf '[FAIL] No DB_PASSWORD in database.conf after run 1\n' >&2; exit 1; }
printf '[OK] Password stored (%d chars)\n' "${#pass1}"

PGPASSWORD="$pass1" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx -c '\q' \
  || { printf '[FAIL] DB connection failed after run 1\n' >&2; exit 1; }
printf '[OK] DB connection works after run 1\n'

# Insert a marker row; it must survive all subsequent credential operations.
PGPASSWORD="$pass1" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx \
  -c "CREATE TABLE IF NOT EXISTS _install_test (val int); INSERT INTO _install_test VALUES (42);"
printf '[OK] Marker row inserted\n'

# ════════════════════════════════════════════════════════════════════════════════
# TEST 2 — Idempotency (credentials file present)
# ════════════════════════════════════════════════════════════════════════════════
printf '\n[TEST 2] Idempotency — credentials file present\n'
nx_configure_postgresql

pass2="$(_read_stored_pass)"
[[ "$pass1" == "$pass2" ]] \
  || { printf '[FAIL] Password changed between run 1 (%s...) and run 2 (%s...)\n' \
       "${pass1:0:6}" "${pass2:0:6}" >&2; exit 1; }
printf '[OK] Password unchanged (truly idempotent)\n'

PGPASSWORD="$pass2" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx -c '\q' \
  || { printf '[FAIL] DB connection failed after run 2\n' >&2; exit 1; }
printf '[OK] DB connection works after run 2\n'

row_count="$(PGPASSWORD="$pass2" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx \
  -t -A -c "SELECT count(*) FROM _install_test WHERE val=42;" || echo '0')"
[[ "$row_count" -ge 1 ]] \
  || { printf '[FAIL] Marker row missing after run 2\n' >&2; exit 1; }
printf '[OK] Existing data preserved after run 2\n'

# ════════════════════════════════════════════════════════════════════════════════
# TEST 3 — .env / database.conf consistency
# ════════════════════════════════════════════════════════════════════════════════
printf '\n[TEST 3] .env / database.conf consistency\n'
env_pass="$(grep '^DB_PASSWORD=' /var/www/nexvary-cx/.env 2>/dev/null \
  | cut -d'=' -f2- | tr -d '[:space:]' || echo '')"
[[ "$env_pass" == "$pass2" ]] \
  || { printf '[FAIL] .env DB_PASSWORD (%s...) differs from database.conf (%s...)\n' \
       "${env_pass:0:6}" "${pass2:0:6}" >&2; exit 1; }
printf '[OK] .env DB_PASSWORD matches database.conf\n'

# ════════════════════════════════════════════════════════════════════════════════
# TEST 4 — No pg_shadow hash contamination
# ════════════════════════════════════════════════════════════════════════════════
printf '\n[TEST 4] No pg_shadow hash contamination\n'
if echo "$pass2" | grep -qE '^(md5[0-9a-f]{32}|SCRAM-SHA-256\$)'; then
  printf '[FAIL] Stored password looks like a pg_shadow hash verifier, not plaintext\n' >&2
  exit 1
fi
printf '[OK] Stored password is not a pg_shadow hash\n'

# ════════════════════════════════════════════════════════════════════════════════
# TEST 5 — Lost credentials recovery
#
# Scenario: the role and database already exist, but /etc/nexvary/database.conf
# was deleted (e.g. accidental removal, re-image of /etc).  The installer must
# generate a new password, ALTER USER, store it, and leave application data
# intact.
# ════════════════════════════════════════════════════════════════════════════════
printf '\n[TEST 5] Lost credentials recovery\n'
rm -f /etc/nexvary/database.conf

nx_configure_postgresql

pass3="$(_read_stored_pass)"
[[ -n "$pass3" ]] \
  || { printf '[FAIL] No DB_PASSWORD in database.conf after recovery run\n' >&2; exit 1; }
printf '[OK] New password generated (%d chars)\n' "${#pass3}"

PGPASSWORD="$pass3" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx -c '\q' \
  || { printf '[FAIL] DB connection failed after recovery run\n' >&2; exit 1; }
printf '[OK] New credentials authenticate successfully\n'

row_count="$(PGPASSWORD="$pass3" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx \
  -t -A -c "SELECT count(*) FROM _install_test WHERE val=42;" || echo '0')"
[[ "$row_count" -ge 1 ]] \
  || { printf '[FAIL] Marker row lost after credential recovery\n' >&2; exit 1; }
printf '[OK] Application data preserved through credential rotation\n'

printf '\n[PASS] All PostgreSQL idempotency and recovery checks passed\n'
