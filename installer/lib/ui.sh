#!/usr/bin/env bash
# Terminal UI helpers — colors, logging, progress

set -euo pipefail

# ── ANSI colors (disabled automatically when not a TTY) ───────────────────────
if [[ -t 1 ]]; then
  C_RESET='\033[0m'
  C_BOLD='\033[1m'
  C_GREEN='\033[0;32m'
  C_CYAN='\033[0;36m'
  C_YELLOW='\033[0;33m'
  C_RED='\033[0;31m'
  C_SILVER='\033[0;37m'
  C_DIM='\033[2m'
else
  C_RESET='' C_BOLD='' C_GREEN='' C_CYAN='' C_YELLOW='' C_RED='' C_SILVER='' C_DIM=''
fi

# ── Logging ───────────────────────────────────────────────────────────────────

nx_banner() {
  echo ""
  printf "${C_BOLD}${C_GREEN}  NEXVARY CX Cloud — Installer${C_RESET}\n"
  printf "${C_DIM}  nexvary.com | github.com/nexvary/NEXVARY-CX-Cloud${C_RESET}\n"
  echo ""
}

nx_step() {
  printf "\n${C_CYAN}${C_BOLD}[→] %s${C_RESET}\n" "$*"
}

nx_info() {
  printf "    ${C_SILVER}%s${C_RESET}\n" "$*"
}

nx_ok() {
  printf "    ${C_GREEN}✓ %s${C_RESET}\n" "$*"
}

nx_warn() {
  printf "    ${C_YELLOW}⚠ %s${C_RESET}\n" "$*" >&2
}

nx_error() {
  printf "\n${C_RED}${C_BOLD}[✗] ERROR: %s${C_RESET}\n\n" "$*" >&2
}

nx_fatal() {
  nx_error "$*"
  exit 1
}

nx_section() {
  echo ""
  printf "${C_BOLD}${C_SILVER}═══ %s ═══${C_RESET}\n" "$*"
}

nx_done() {
  printf "\n${C_GREEN}${C_BOLD}✓ %s${C_RESET}\n\n" "$*"
}

# ── Spinner for long operations ────────────────────────────────────────────────

_NX_SPINNER_PID=''

nx_spinner_start() {
  if [[ -t 1 ]]; then
    local msg="${1:-Working…}"
    local frames=('⣾' '⣽' '⣻' '⢿' '⡿' '⣟' '⣯' '⣷')
    local i=0
    while true; do
      printf "\r    ${C_CYAN}%s${C_RESET} %s" "${frames[$((i % ${#frames[@]}))]}" "$msg"
      i=$((i + 1))
      sleep 0.12
    done &
    _NX_SPINNER_PID=$!
  fi
}

nx_spinner_stop() {
  if [[ -n "$_NX_SPINNER_PID" ]]; then
    kill "$_NX_SPINNER_PID" 2>/dev/null
    wait "$_NX_SPINNER_PID" 2>/dev/null || true
    _NX_SPINNER_PID=''
    printf "\r\033[2K"
  fi
}

# ── Confirmation prompt ────────────────────────────────────────────────────────

nx_confirm() {
  local prompt="${1:-Continue?} [y/N] "
  local answer
  if [[ -t 0 ]]; then
    printf "${C_YELLOW}%s${C_RESET}" "$prompt"
    read -r answer
    [[ "${answer,,}" =~ ^y(es)?$ ]]
  else
    # Non-interactive: require explicit NX_YES=1
    [[ "${NX_YES:-}" == '1' ]]
  fi
}

# ── Root check ────────────────────────────────────────────────────────────────

nx_require_root() {
  if [[ "$EUID" -ne 0 ]]; then
    nx_fatal "This installer must be run as root (sudo bash $0)"
  fi
}
