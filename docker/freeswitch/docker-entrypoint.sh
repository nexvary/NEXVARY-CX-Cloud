#!/usr/bin/env bash
# FreeSWITCH Docker entrypoint
# Initialises /etc/freeswitch from the image defaults on first run,
# injects secrets, then execs FreeSWITCH in the foreground.
set -euo pipefail

FS_CONF="${FREESWITCH_CONF_DIR:-/etc/freeswitch}"
FS_LOG="${FREESWITCH_LOG_DIR:-/var/log/freeswitch}"
FS_DATA='/var/lib/freeswitch'
FS_DEFAULT_CONF='/usr/share/nexvary/freeswitch-defaults'
FS_OVERRIDE_CONF='/usr/share/nexvary/freeswitch-overrides'

# ── Initialise config volume on first run ─────────────────────────────────────
if [[ ! -f "${FS_CONF}/freeswitch.xml" ]]; then
  printf '[FS-INIT] Populating %s from image defaults...\n' "${FS_CONF}"
  cp -r "${FS_DEFAULT_CONF}/." "${FS_CONF}/"
fi

# Apply our overlay (event_socket.conf.xml, sofia profile tweaks, etc.)
if [[ -d "${FS_OVERRIDE_CONF}" ]]; then
  cp -r "${FS_OVERRIDE_CONF}/." "${FS_CONF}/"
fi

# ── Write vars.xml with secrets from environment ──────────────────────────────
# FreeSWITCH resolves $${varname} at XML parse time from this file.
: "${FREESWITCH_ESL_PASSWORD:?FREESWITCH_ESL_PASSWORD must be set}"
: "${DOMAIN:=localhost}"

cat > "${FS_CONF}/vars.xml" <<VARS
<include>
  <!-- Injected by Docker entrypoint — do not edit manually -->
  <X-PRE-PROCESS cmd="set" data="default_password=${FREESWITCH_ESL_PASSWORD}"/>
  <X-PRE-PROCESS cmd="set" data="domain=${DOMAIN}"/>
  <X-PRE-PROCESS cmd="set" data="local_ip_v4=\$\${local_ip_v4}"/>
  <X-PRE-PROCESS cmd="set" data="external_rtp_ip=auto-nat"/>
  <X-PRE-PROCESS cmd="set" data="external_sip_ip=auto-nat"/>
  <X-PRE-PROCESS cmd="set" data="internal_sip_port=5060"/>
  <X-PRE-PROCESS cmd="set" data="internal_tls_port=5061"/>
  <X-PRE-PROCESS cmd="set" data="external_sip_port=5080"/>
  <X-PRE-PROCESS cmd="set" data="external_tls_port=5081"/>
  <X-PRE-PROCESS cmd="set" data="rtp_start_port=16384"/>
  <X-PRE-PROCESS cmd="set" data="rtp_end_port=16484"/>
</include>
VARS

# ── Ensure required directories exist ─────────────────────────────────────────
mkdir -p "${FS_LOG}" "${FS_DATA}/recordings" "${FS_DATA}/storage" "${FS_DATA}/db"

printf '[FS-INIT] Starting FreeSWITCH (domain=%s)...\n' "${DOMAIN}"
exec freeswitch \
  -nonat \
  -nf \
  -nc \
  -conf   "${FS_CONF}" \
  -log    "${FS_LOG}" \
  -db     "${FS_DATA}/db" \
  -recordings "${FS_DATA}/recordings" \
  -storage    "${FS_DATA}/storage" \
  "$@"
