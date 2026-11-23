#!/usr/bin/env bash
# Tests that nx_configure_postgresql is idempotent:
# running it twice must produce identical credentials and valid authentication.
#
# Run inside a Debian 13 (trixie) Docker container:
#   docker run --rm --privileged -v "$PWD/installer:/installer:ro" \
#     debian:trixie bash /installer/tests/test-postgres-idempotency.sh
set -euo pipefail

INSTALLER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ── Minimal stubs for ui.sh functions used by app.sh ─────────────────────────
nx_step()           { printf '  STEP  %s\n' "$*"; }
nx_ok()             { printf '  [OK]  %s\n' "$*"; }
nx_info()           { printf '  [INF] %s\n' "$*"; }
nx_warn()           { printf '  [WRN] %s\n' "$*"; }
nx_error()          { printf '  [ERR] %s\n' "$*" >&2; }
nx_fatal()          { printf '  [FATAL] %s\n' "$*" >&2; exit 1; }
nx_spinner_start()  { :; }
nx_spinner_stop()   { :; }
nx_log_passthrough(){ cat > /dev/null; }

# ── Install PostgreSQL ────────────────────────────────────────────────────────
export DEBIAN_FRONTEND=noninteractive
printf '[INFO] Installing PostgreSQL 16...\n'
apt-get update -qq
apt-get install -y -q postgresql-16 postgresql-client-16 openssl 2>/dev/null
printf '[INFO] PostgreSQL installed\n'

# Start the cluster (Docker containers do not auto-start services)
pg_ctlcluster 16 main start 2>/dev/null || service postgresql start 2>/dev/null || true
sleep 2
if ! pg_isready -q 2>/dev/null; then
  printf '[FAIL] PostgreSQL did not start\n' >&2
  exit 1
fi
printf '[INFO] PostgreSQL is ready\n'

# ── Set up required directories and user ─────────────────────────────────────
mkdir -p /var/www/nexvary-cx /etc/nexvary /var/log
useradd --system --home-dir /var/www/nexvary-cx --no-create-home \
  --shell /sbin/nologin nexvary 2>/dev/null || true

# Minimal .env stub so the sed writes in nx_configure_postgresql have a file
cat > /var/www/nexvary-cx/.env <<'ENV'
DB_PASSWORD=
DB_USERNAME=nexvary_cx
DB_DATABASE=nexvary_cx
ENV

# ── Source app.sh ─────────────────────────────────────────────────────────────
# shellcheck source=installer/lib/app.sh
source "${INSTALLER_DIR}/lib/app.sh"

# ── Run 1: fresh configuration ────────────────────────────────────────────────
printf '\n[TEST] Run 1 — fresh configuration\n'
nx_configure_postgresql
pass1="$(grep '^DB_PASSWORD=' /etc/nexvary/database.conf 2>/dev/null | cut -d'=' -f2- | tr -d '[:space:]' || echo '')"
[[ -n "$pass1" ]] || { printf '[FAIL] No DB_PASSWORD in database.conf after run 1\n' >&2; exit 1; }
printf '[OK] Password stored (%d chars)\n' "${#pass1}"

# Verify the credentials work
PGPASSWORD="$pass1" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx -c '\q' 2>/dev/null \
  || { printf '[FAIL] DB connection failed after run 1\n' >&2; exit 1; }
printf '[OK] Database connection works after run 1\n'

# ── Run 2: idempotency ────────────────────────────────────────────────────────
printf '\n[TEST] Run 2 — idempotency (user already exists)\n'
nx_configure_postgresql
pass2="$(grep '^DB_PASSWORD=' /etc/nexvary/database.conf 2>/dev/null | cut -d'=' -f2- | tr -d '[:space:]' || echo '')"
[[ -n "$pass2" ]] || { printf '[FAIL] No DB_PASSWORD in database.conf after run 2\n' >&2; exit 1; }

[[ "$pass1" == "$pass2" ]] \
  || { printf '[FAIL] Password changed between run 1 (%s...) and run 2 (%s...)\n' \
       "${pass1:0:6}" "${pass2:0:6}" >&2; exit 1; }
printf '[OK] Password unchanged (idempotent)\n'

# Verify credentials still work after the second run
PGPASSWORD="$pass2" psql -h 127.0.0.1 -U nexvary_cx -d nexvary_cx -c '\q' 2>/dev/null \
  || { printf '[FAIL] DB connection failed after run 2\n' >&2; exit 1; }
printf '[OK] Database connection works after run 2\n'

# ── Verify .env was updated with the correct (plaintext) password ─────────────
printf '\n[TEST] .env DB_PASSWORD matches database.conf\n'
env_pass="$(grep '^DB_PASSWORD=' /var/www/nexvary-cx/.env 2>/dev/null | cut -d'=' -f2- | tr -d '[:space:]' || echo '')"
[[ "$env_pass" == "$pass2" ]] \
  || { printf '[FAIL] .env DB_PASSWORD (%s...) differs from database.conf (%s...)\n' \
       "${env_pass:0:6}" "${pass2:0:6}" >&2; exit 1; }
printf '[OK] .env DB_PASSWORD matches stored credentials\n'

# ── Verify no pg_shadow hash contamination ────────────────────────────────────
printf '\n[TEST] Stored password is not a pg_shadow hash\n'
# pg_shadow hashes start with "md5" or "SCRAM-SHA-256$"
if echo "$pass2" | grep -qE '^(md5[0-9a-f]{32}|SCRAM-SHA-256\$)'; then
  printf '[FAIL] DB_PASSWORD looks like a pg_shadow hash verifier, not a plaintext password\n' >&2
  exit 1
fi
printf '[OK] Stored password does not look like a pg_shadow hash\n'

printf '\n[PASS] All PostgreSQL idempotency checks passed\n'
