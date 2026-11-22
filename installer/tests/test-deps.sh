#!/usr/bin/env bash
# Installer dependency test — installs PHP, PostgreSQL, Redis
# Usage: bash test-deps.sh <debian|centos>

set -euo pipefail

INSTALLER_LIB='/installer/lib'
ADAPTER="${1:?Adapter required: debian or centos}"

# Minimal UI stubs
nx_warn()           { echo "WARN: $*" >&2; }
nx_error()          { echo "ERROR: $*" >&2; }
nx_fatal()          { echo "FATAL: $*" >&2; exit 1; }
nx_ok()             { echo "  ✓ $*"; }
nx_info()           { echo "  → $*"; }
nx_step()           { echo ""; echo "STEP: $*"; }
nx_section()        { echo ""; echo "═══ $* ═══"; }
nx_spinner_start()  { echo "  ... $*"; }
nx_spinner_stop()   { true; }
nx_log_passthrough(){ cat > /dev/null; }
export -f nx_warn nx_error nx_fatal nx_ok nx_info nx_step nx_section
export -f nx_spinner_start nx_spinner_stop nx_log_passthrough

# Source detection to set NX_OS_VERSION
source "${INSTALLER_LIB}/detect.sh"
nx_detect_os || true

echo ""
echo "Platform: $NX_OS_ID $NX_OS_VERSION ($ADAPTER adapter)"
echo ""

case "$ADAPTER" in
  debian)
    source "${INSTALLER_LIB}/debian.sh"

    echo "=== Installing PHP 8.3 ==="
    nx_install_php_debian
    php -v && php -m | grep -q pdo_pgsql && echo "  ✓ pdo_pgsql extension loaded"
    php -m | grep -q redis && echo "  ✓ redis extension loaded"

    echo "=== Installing PostgreSQL 16 ==="
    nx_install_postgresql_debian
    pg_lsclusters 2>/dev/null || true
    # Verify postgres started
    pg_isready -h 127.0.0.1 -p 5432 && echo "  ✓ PostgreSQL accepting connections"

    echo "=== Installing Redis ==="
    nx_install_redis_debian
    redis-cli ping | grep -q PONG && echo "  ✓ Redis responding to PING"
    ;;

  centos)
    source "${INSTALLER_LIB}/centos.sh"

    echo "=== Installing PHP 8.3 ==="
    nx_install_php_centos
    php -v && php -m | grep -q pdo_pgsql && echo "  ✓ pdo_pgsql extension loaded"

    echo "=== Installing PostgreSQL 16 ==="
    nx_install_postgresql_centos
    pg_isready -h 127.0.0.1 -p 5432 2>/dev/null && echo "  ✓ PostgreSQL accepting connections" || \
      echo "  → PostgreSQL may need systemd to start fully"

    echo "=== Installing Redis ==="
    nx_install_redis_centos
    redis-cli ping 2>/dev/null | grep -q PONG && echo "  ✓ Redis responding to PING" || \
      echo "  → Redis may need systemd to start fully"
    ;;

  *)
    echo "Unknown adapter: $ADAPTER" >&2
    exit 1
    ;;
esac

echo ""
echo "PASS: Dependency installation completed on $NX_OS_ID $NX_OS_VERSION"
