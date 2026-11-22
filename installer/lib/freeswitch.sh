#!/usr/bin/env bash
# FreeSWITCH installation module
# Supports:
#   - Debian 12/13 via SignalWire FOSS repository (requires SW_TOKEN)
#   - CentOS Stream 9/10 via SignalWire FOSS repository (requires SW_TOKEN)
# A SignalWire FOSS token is free and obtained at: https://id.signalwire.com/sign_up

set -euo pipefail

readonly NX_FS_VERSION_MIN='1.10'
readonly NX_FS_ESL_PORT=8021

# ── Token validation ──────────────────────────────────────────────────────────

nx_check_signalwire_token() {
  if [[ -z "${NX_SW_TOKEN:-}" ]]; then
    nx_warn "No SignalWire FOSS token provided (NX_SW_TOKEN environment variable)."
    nx_warn "FreeSWITCH requires a free token from: https://id.signalwire.com/sign_up"
    nx_warn ""
    nx_warn "Re-run with: sudo NX_SW_TOKEN=your_token nexvary install"
    if [[ "${NX_FS_SKIP:-}" == '1' ]]; then
      nx_warn "NX_FS_SKIP=1 set — skipping FreeSWITCH installation."
      return 1
    fi
    nx_fatal "Cannot install FreeSWITCH without a SignalWire FOSS token."
  fi

  nx_info "Validating SignalWire token…"
  local status
  status="$(curl -sSo /dev/null -w '%{http_code}' \
    --max-time 10 \
    -u "signalwire:${NX_SW_TOKEN}" \
    "https://freeswitch.signalwire.com/repo/deb/debian-release/" 2>/dev/null || echo '000')"

  if [[ "$status" == '200' || "$status" == '301' || "$status" == '302' ]]; then
    nx_ok "SignalWire token validated"
  else
    nx_error "SignalWire token validation failed (HTTP $status). Verify your token."
    nx_error "Get a free token at: https://id.signalwire.com/sign_up"
    nx_fatal "Invalid or expired SignalWire FOSS token."
  fi
}

# ── Debian/Ubuntu installation ────────────────────────────────────────────────

nx_install_freeswitch_debian() {
  nx_step "Installing FreeSWITCH via SignalWire repository"

  if nx_freeswitch_running; then
    nx_ok "FreeSWITCH is already installed and running"
    return
  fi

  nx_check_signalwire_token || return 0

  local keyring='/usr/share/keyrings/signalwire-freeswitch-repo.gpg'
  if [[ ! -f "$keyring" ]]; then
    curl -sSL \
      --user "signalwire:${NX_SW_TOKEN}" \
      "https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg" \
      | gpg --dearmor -o "$keyring"
    chmod 644 "$keyring"
  fi

  if [[ ! -f /etc/apt/sources.list.d/freeswitch.list ]]; then
    # Store credentials securely (not world-readable)
    local authconf='/etc/apt/auth.conf.d/freeswitch.conf'
    mkdir -p /etc/apt/auth.conf.d
    printf 'machine freeswitch.signalwire.com login signalwire password %s\n' \
      "$NX_SW_TOKEN" > "$authconf"
    chmod 600 "$authconf"

    printf 'deb [signed-by=%s] https://freeswitch.signalwire.com/repo/deb/debian-release/ bookworm main\n' \
      "$keyring" > /etc/apt/sources.list.d/freeswitch.list

    DEBIAN_FRONTEND=noninteractive apt-get -y -q update 2>&1 | nx_log_passthrough
  fi

  nx_spinner_start "Installing FreeSWITCH packages…"
  DEBIAN_FRONTEND=noninteractive apt-get -y -q install \
    freeswitch freeswitch-meta-codecs freeswitch-meta-conf \
    freeswitch-mod-commands freeswitch-mod-dptools freeswitch-mod-sofia \
    freeswitch-mod-event-socket freeswitch-mod-json-cdr \
    2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_configure_freeswitch_esl
  systemctl enable freeswitch --now
  nx_ok "FreeSWITCH $(freeswitch -version 2>/dev/null | head -1) installed"
}

# ── CentOS Stream installation ────────────────────────────────────────────────

nx_install_freeswitch_centos() {
  nx_step "Installing FreeSWITCH via SignalWire repository"

  if nx_freeswitch_running; then
    nx_ok "FreeSWITCH is already installed and running"
    return
  fi

  nx_check_signalwire_token || return 0

  local stream="${NX_OS_VERSION:-9}"
  local repofile="/etc/yum.repos.d/freeswitch.repo"

  if [[ ! -f "$repofile" ]]; then
    local keyurl="https://freeswitch.signalwire.com/repo/rpm/centos-release/SW_Repo_Key.gpg"
    rpm --import "$keyurl" 2>/dev/null || true

    cat > "$repofile" <<EOF
[freeswitch]
name=FreeSWITCH SignalWire Repo
baseurl=https://signalwire:${NX_SW_TOKEN}@freeswitch.signalwire.com/repo/rpm/centos-release/
enabled=1
gpgcheck=1
gpgkey=$keyurl
EOF
    chmod 600 "$repofile"
  fi

  nx_spinner_start "Installing FreeSWITCH packages…"
  dnf -y -q install freeswitch freeswitch-meta-codecs freeswitch-config-vanilla \
    2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_configure_freeswitch_esl
  systemctl enable freeswitch --now
  nx_ok "FreeSWITCH installed"
}

# ── Configure ESL ─────────────────────────────────────────────────────────────

nx_configure_freeswitch_esl() {
  local esl_conf='/etc/freeswitch/autoload_configs/event_socket.conf.xml'
  local acl_conf='/etc/freeswitch/autoload_configs/acl.conf.xml'

  # Generate a strong ESL password (not stored in env/logs)
  local esl_password
  esl_password="$(openssl rand -base64 32 | tr -d '/+=\n' | head -c 40)"

  if [[ -f "$esl_conf" ]]; then
    # Update the password in existing config
    sed -i "s|<param name=\"password\" value=\"[^\"]*\"/>|<param name=\"password\" value=\"${esl_password}\"/>|" \
      "$esl_conf" 2>/dev/null || true
    # Restrict to localhost
    sed -i "s|<param name=\"listen-ip\" value=\"[^\"]*\"/>|<param name=\"listen-ip\" value=\"127.0.0.1\"/>|" \
      "$esl_conf" 2>/dev/null || true
  fi

  # Store ESL password in a secrets file (root-only, not in app .env)
  local secrets_dir='/etc/nexvary'
  mkdir -p "$secrets_dir"
  chmod 700 "$secrets_dir"

  local secrets_file="${secrets_dir}/freeswitch.conf"
  cat > "$secrets_file" <<EOF
# NEXVARY CX Cloud — FreeSWITCH configuration
# Generated by installer on $(date -Iseconds)
FREESWITCH_HOST=127.0.0.1
FREESWITCH_ESL_PORT=${NX_FS_ESL_PORT}
FREESWITCH_ESL_PASSWORD=${esl_password}
EOF
  chmod 640 "$secrets_file"
  chown root:www-data "$secrets_file" 2>/dev/null || chown root:nginx "$secrets_file" 2>/dev/null || true

  nx_ok "FreeSWITCH ESL configured on 127.0.0.1:${NX_FS_ESL_PORT}"
}

# ── Health check ──────────────────────────────────────────────────────────────

nx_freeswitch_running() {
  systemctl is-active --quiet freeswitch 2>/dev/null
}

nx_verify_freeswitch() {
  if [[ "${NX_FS_SKIP:-}" == '1' ]]; then
    nx_warn "FreeSWITCH verification skipped (NX_FS_SKIP=1)"
    return 0
  fi

  nx_step "Verifying FreeSWITCH"
  if ! nx_freeswitch_running; then
    nx_error "FreeSWITCH service is not running."
    systemctl status freeswitch --no-pager 2>/dev/null || true
    nx_fatal "FreeSWITCH did not start. Check /var/log/freeswitch/freeswitch.log"
  fi

  local esl_port="${NX_FS_ESL_PORT}"
  if ss -ltnp 2>/dev/null | grep -q ":${esl_port}\b"; then
    nx_ok "FreeSWITCH ESL is listening on 127.0.0.1:${esl_port}"
  else
    nx_warn "FreeSWITCH ESL port ${esl_port} is not bound yet — it may still be starting."
  fi
}
