#!/usr/bin/env bash
# FreeSWITCH installation module
#
# Two installation paths:
#   1. SignalWire binary repository — fast (~2 min), requires a free token.
#      Obtain one at https://id.signalwire.com/sign_up
#      Set NX_SW_TOKEN or pass --sw-token=TOKEN to the installer.
#
#   2. Source build from GitHub — no account needed, ~15-25 min build time,
#      requires ~2 GB extra disk.  Used automatically when NX_SW_TOKEN is not
#      set.  Pinned to NX_FS_BUILD_VERSION for reproducibility.
#
# Required modules verified after start: mod_sofia (SIP), mod_event_socket (ESL)

set -euo pipefail

readonly NX_FS_VERSION_MIN='1.10'
readonly NX_FS_BUILD_VERSION='v1.10.11'
readonly NX_FS_INSTALL_PREFIX='/usr/local/freeswitch'
readonly NX_FS_ESL_PORT=8021

# ── Entry point: Debian/Ubuntu ─────────────────────────────────────────────────

nx_install_freeswitch_debian() {
  nx_step "Installing FreeSWITCH"

  if nx_freeswitch_running; then
    nx_ok "FreeSWITCH is already installed and running"
    return
  fi

  if [[ -n "${NX_SW_TOKEN:-}" ]]; then
    _nx_install_freeswitch_signalwire_debian
  else
    nx_warn "NX_SW_TOKEN not set — building FreeSWITCH ${NX_FS_BUILD_VERSION} from source."
    nx_warn "This requires ~2 GB extra disk and 15–25 minutes of build time."
    nx_install_freeswitch_from_source
  fi
}

# ── Path 1: SignalWire binary repository ──────────────────────────────────────

_nx_install_freeswitch_signalwire_debian() {
  nx_step "Installing FreeSWITCH via SignalWire repository"

  nx_info "Validating SignalWire token…"
  local status
  status="$(curl -sSo /dev/null -w '%{http_code}' \
    --max-time 10 \
    -u "signalwire:${NX_SW_TOKEN}" \
    "https://freeswitch.signalwire.com/repo/deb/debian-release/" 2>/dev/null || echo '000')"

  if [[ "$status" != '200' && "$status" != '301' && "$status" != '302' ]]; then
    nx_error "SignalWire token validation failed (HTTP $status)."
    nx_error "Get a free token at: https://id.signalwire.com/sign_up"
    nx_fatal "Invalid or expired SignalWire FOSS token."
  fi
  nx_ok "SignalWire token validated"

  local keyring='/usr/share/keyrings/signalwire-freeswitch-repo.gpg'
  if [[ ! -f "$keyring" ]]; then
    curl -sSL \
      --user "signalwire:${NX_SW_TOKEN}" \
      "https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg" \
      | gpg --dearmor -o "$keyring"
    chmod 644 "$keyring"
  fi

  if [[ ! -f /etc/apt/sources.list.d/freeswitch.list ]]; then
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
  nx_ok "FreeSWITCH installed via SignalWire repository"
}

# ── Path 2: Source build (no token) ───────────────────────────────────────────

nx_install_freeswitch_from_source() {
  nx_step "Building FreeSWITCH ${NX_FS_BUILD_VERSION} from source"

  # Build dependencies — Debian 12 (Bookworm) and 13 (Trixie)
  # libncurses-dev replaces libncurses5-dev (renamed in Bookworm+)
  # libpcre2-dev replaces libpcre3-dev (deprecated)
  # libtool-bin provides libtoolize separately from libtool on Debian 12+
  nx_spinner_start "Installing FreeSWITCH build dependencies…"
  DEBIAN_FRONTEND=noninteractive apt-get -y -q --no-install-recommends install \
    build-essential pkg-config libtool-bin autoconf automake cmake git-core \
    uuid-dev zlib1g-dev libjpeg-dev libsqlite3-dev libssl-dev libpcre2-dev \
    libopus-dev libedit-dev libncurses-dev libyaml-dev libxml2-dev \
    libcurl4-openssl-dev libexpat1-dev libtiff-dev \
    libspeex-dev libspeexdsp-dev libsndfile1-dev libasound2-dev \
    python3-dev liblua5.4-dev libpq-dev \
    2>&1 | nx_log_passthrough
  nx_spinner_stop

  # Dedicated system user (non-root, no login)
  if ! id freeswitch &>/dev/null; then
    useradd --system --home-dir "${NX_FS_INSTALL_PREFIX}" \
      --no-create-home --shell /usr/sbin/nologin \
      --comment 'FreeSWITCH softswitch' freeswitch
  fi

  local src_dir='/usr/src/freeswitch-build'

  if [[ ! -d "${src_dir}/.git" ]]; then
    nx_spinner_start "Cloning FreeSWITCH ${NX_FS_BUILD_VERSION}…"
    git clone --depth=1 --branch "${NX_FS_BUILD_VERSION}" \
      https://github.com/signalwire/freeswitch.git "${src_dir}" --quiet \
      2>&1 | nx_log_passthrough
    nx_spinner_stop
  else
    nx_info "FreeSWITCH source already present at ${src_dir}"
  fi

  pushd "${src_dir}" > /dev/null

  nx_spinner_start "Bootstrapping FreeSWITCH…"
  ./bootstrap.sh -j 2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_spinner_start "Configuring FreeSWITCH…"
  ./configure \
    --prefix="${NX_FS_INSTALL_PREFIX}" \
    --enable-core-pgsql-support \
    --disable-dependency-tracking \
    2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_spinner_start "Compiling FreeSWITCH (this takes ~15–25 minutes)…"
  make -j"$(nproc)" 2>&1 | nx_log_passthrough
  nx_spinner_stop

  nx_spinner_start "Installing FreeSWITCH to ${NX_FS_INSTALL_PREFIX}…"
  make install 2>&1 | nx_log_passthrough
  make cd-sounds-install cd-moh-install 2>&1 | nx_log_passthrough
  nx_spinner_stop

  popd > /dev/null

  # Ownership and PATH symlinks
  mkdir -p "${NX_FS_INSTALL_PREFIX}/run" "${NX_FS_INSTALL_PREFIX}/log"
  chown -R freeswitch:freeswitch "${NX_FS_INSTALL_PREFIX}"

  ln -sf "${NX_FS_INSTALL_PREFIX}/bin/freeswitch" /usr/local/bin/freeswitch 2>/dev/null || true
  ln -sf "${NX_FS_INSTALL_PREFIX}/bin/fs_cli"     /usr/local/bin/fs_cli     2>/dev/null || true

  _nx_write_freeswitch_systemd_unit
  nx_configure_freeswitch_esl

  systemctl daemon-reload
  systemctl enable freeswitch --now
  sleep 5

  nx_ok "FreeSWITCH ${NX_FS_BUILD_VERSION} built and installed to ${NX_FS_INSTALL_PREFIX}"
}

_nx_write_freeswitch_systemd_unit() {
  cat > /etc/systemd/system/freeswitch.service <<SERVICE
[Unit]
Description=FreeSWITCH ${NX_FS_BUILD_VERSION} Softswitch (source build)
Documentation=https://developer.signalwire.com/freeswitch/FreeSWITCH-Explained/
After=network.target postgresql.service redis-server.service
Wants=network.target

[Service]
Type=forking
PIDFile=${NX_FS_INSTALL_PREFIX}/run/freeswitch.pid
ExecStartPre=+/bin/mkdir -p ${NX_FS_INSTALL_PREFIX}/run ${NX_FS_INSTALL_PREFIX}/log
ExecStartPre=+/bin/chown -R freeswitch:freeswitch ${NX_FS_INSTALL_PREFIX}/run ${NX_FS_INSTALL_PREFIX}/log
ExecStart=${NX_FS_INSTALL_PREFIX}/bin/freeswitch -u freeswitch -g daemon -nc -nonat -nf
ExecReload=/bin/kill -HUP \$MAINPID
ExecStop=${NX_FS_INSTALL_PREFIX}/bin/freeswitch -stop
Restart=on-failure
RestartSec=10
LimitNOFILE=65535
LimitNPROC=65535

[Install]
WantedBy=multi-user.target
SERVICE
}

# ── CentOS Stream installation (token required) ───────────────────────────────

nx_install_freeswitch_centos() {
  nx_step "Installing FreeSWITCH via SignalWire repository"

  if nx_freeswitch_running; then
    nx_ok "FreeSWITCH is already installed and running"
    return
  fi

  if [[ -z "${NX_SW_TOKEN:-}" ]]; then
    nx_fatal "CentOS/RHEL FreeSWITCH installation requires a SignalWire FOSS token (NX_SW_TOKEN)."
  fi

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

# ── Configure ESL (loopback, strong password, root-only secrets) ──────────────

nx_configure_freeswitch_esl() {
  # Locate the ESL config — package install uses /etc/freeswitch/,
  # source build uses ${NX_FS_INSTALL_PREFIX}/conf/
  local esl_conf
  if [[ -f '/etc/freeswitch/autoload_configs/event_socket.conf.xml' ]]; then
    esl_conf='/etc/freeswitch/autoload_configs/event_socket.conf.xml'
  elif [[ -f "${NX_FS_INSTALL_PREFIX}/conf/autoload_configs/event_socket.conf.xml" ]]; then
    esl_conf="${NX_FS_INSTALL_PREFIX}/conf/autoload_configs/event_socket.conf.xml"
  else
    nx_warn "FreeSWITCH ESL config not found — skipping ESL configuration"
    return 0
  fi

  local esl_password
  esl_password="$(openssl rand -base64 32 | tr -d '/+=\n' | head -c 40)"

  # Restrict to localhost and set generated password
  sed -i "s|<param name=\"listen-ip\" value=\"[^\"]*\"/>|<param name=\"listen-ip\" value=\"127.0.0.1\"/>|" \
    "$esl_conf" 2>/dev/null || true
  sed -i "s|<param name=\"password\" value=\"[^\"]*\"/>|<param name=\"password\" value=\"${esl_password}\"/>|" \
    "$esl_conf" 2>/dev/null || true

  # Write a root-only secrets file that app.sh reads into .env
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
    local log_path
    log_path="${NX_FS_INSTALL_PREFIX}/log/freeswitch.log"
    [[ -f '/var/log/freeswitch/freeswitch.log' ]] && log_path='/var/log/freeswitch/freeswitch.log'
    nx_fatal "FreeSWITCH did not start. Check ${log_path}"
  fi

  nx_ok "FreeSWITCH service is active"

  local esl_port="${NX_FS_ESL_PORT}"
  if ss -ltnp 2>/dev/null | grep -q ":${esl_port}\b"; then
    nx_ok "FreeSWITCH ESL is listening on 127.0.0.1:${esl_port}"
  else
    nx_warn "FreeSWITCH ESL port ${esl_port} is not bound yet — service may still be loading."
  fi

  # Verify required modules via fs_cli if available
  if command -v fs_cli &>/dev/null; then
    local sofia_ok esl_ok
    sofia_ok="$(fs_cli -x 'module_exists mod_sofia' 2>/dev/null || echo 'unknown')"
    esl_ok="$(fs_cli -x 'module_exists mod_event_socket' 2>/dev/null || echo 'unknown')"
    if [[ "$sofia_ok" == *'true'* ]]; then
      nx_ok "mod_sofia is loaded"
    else
      nx_warn "Could not confirm mod_sofia via fs_cli (got: ${sofia_ok})"
    fi
    if [[ "$esl_ok" == *'true'* ]]; then
      nx_ok "mod_event_socket is loaded"
    else
      nx_warn "Could not confirm mod_event_socket via fs_cli (got: ${esl_ok})"
    fi
  fi
}
