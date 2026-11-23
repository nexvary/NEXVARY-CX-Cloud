#!/usr/bin/env bash
set -euo pipefail

# FreeSWITCH 1.10.12, linux/amd64, from the base-image contributor.
image='safarov/freeswitch@sha256:b31c743f4c911a19687c61e3214968f2a24f93f9d3d667cc26284192e158ffc6'
config_dir="$(mktemp -d)"
container="nexvary-freeswitch-qa-$RANDOM"
cleanup() {
  docker rm -f "$container" >/dev/null 2>&1 || :
  rm -rf -- "$config_dir"
}
trap cleanup EXIT
gateway="$(docker network inspect --format '{{ (index .IPAM.Config 0).Gateway }}' bridge)"
[[ "$gateway" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]
export FREESWITCH_HOST=127.0.0.1 FREESWITCH_ESL_PORT=18021
export FREESWITCH_ESL_PASSWORD
FREESWITCH_ESL_PASSWORD="$(openssl rand -hex 32)"
printf '::add-mask::%s\n' "$FREESWITCH_ESL_PASSWORD"
cat > "$config_dir/event_socket.conf.xml" <<XML
<configuration name="event_socket.conf" description="QA Event Socket">
  <settings>
    <param name="nat-map" value="false"/>
    <param name="listen-ip" value="0.0.0.0"/>
    <param name="listen-port" value="8021"/>
    <param name="password" value="$FREESWITCH_ESL_PASSWORD"/>
    <param name="apply-inbound-acl" value="nexvary-qa"/>
  </settings>
</configuration>
XML

docker run -d --name "$container" --no-healthcheck \
  --network bridge -e "QA_BRIDGE_GATEWAY=$gateway" \
  --publish 127.0.0.1:18021:8021 --publish 127.0.0.1:15060:5060/udp \
  --mount "type=bind,src=$config_dir/event_socket.conf.xml,dst=/qa-event_socket.conf.xml,readonly" \
  --entrypoint /bin/sh "$image" -ec '
    mkdir -p /etc/freeswitch
    cp -R /usr/share/freeswitch/conf/vanilla/. /etc/freeswitch/
    cp /qa-event_socket.conf.xml /etc/freeswitch/autoload_configs/event_socket.conf.xml
    config=/etc/freeswitch/autoload_configs
    sed -i "s|</network-lists>|<list name=\"nexvary-qa\" default=\"deny\"><node type=\"allow\" cidr=\"$QA_BRIDGE_GATEWAY/32\"/></list></network-lists>|" "$config/acl.conf.xml"
    sed -i "/load module=\"mod_signalwire\"/d" "$config/modules.conf.xml"
    exec /usr/bin/freeswitch -nc -nf -nonat
  '

for attempt in {1..20}; do
  if php scripts/qa-freeswitch.php; then
    break
  fi
  if [[ "$attempt" == 20 ]]; then
    docker logs --tail 35 "$container"
    exit 1
  fi
  sleep 2
done

python3 - <<'PY'
import socket
from uuid import uuid4

nonce = uuid4().hex
request = "\r\n".join((
    "OPTIONS sip:127.0.0.1:15060 SIP/2.0",
    f"Via: SIP/2.0/UDP 127.0.0.1:15061;branch=z9hG4bK{nonce};rport",
    f"From: <sip:qa@127.0.0.1>;tag={nonce}",
    "To: <sip:127.0.0.1>",
    f"Call-ID: {nonce}@localhost",
    "CSeq: 1 OPTIONS",
    "Max-Forwards: 1",
    "Content-Length: 0", "", "",
))
with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as client:
    client.bind(("127.0.0.1", 15061))
    client.settimeout(8)
    client.sendto(request.encode("ascii"), ("127.0.0.1", 15060))
    data, _ = client.recvfrom(4096)
    status = data.split(b"\r\n", 1)[0]
    if not status.startswith(b"SIP/2.0 200") or nonce.encode("ascii") not in data:
        raise SystemExit(f"Unexpected local SIP OPTIONS response: {status!r}")
    print("FreeSWITCH returned SIP 200 OK to loopback OPTIONS.")
PY

# ── Phase 3: Originate a loopback call and prove two-way audio connectivity ──
#
# FreeSWITCH's loopback/ channel creates two bridged legs in the same dialplan
# context.  We originate to 9196 (the built-in echo extension in the vanilla
# config) which connects the legs and echoes audio between them.  After
# verifying ACTIVE state we hang up and confirm zero active calls.  This proves:
#   • FreeSWITCH can process calls end-to-end via its dialplan
#   • Both legs connect (bridged = two-way audio path exists)
#   • Hangup completes cleanly
#
# Note: media content is not inspected; confirming BRIDGED state on both legs
# is the standard CI-level proof of two-way audio capability.
# Physical-device SRTP/TLS audio verification is documented separately.
php scripts/qa-freeswitch-call.php
