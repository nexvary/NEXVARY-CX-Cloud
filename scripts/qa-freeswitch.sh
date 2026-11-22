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
  </settings>
</configuration>
XML

docker run -d --name "$container" --no-healthcheck \
  --publish 127.0.0.1:18021:8021 --publish 127.0.0.1:15060:5060/udp \
  --mount "type=bind,src=$config_dir/event_socket.conf.xml,dst=/qa-event_socket.conf.xml,readonly" \
  --entrypoint /bin/sh "$image" -ec '
    mkdir -p /etc/freeswitch
    cp -R /usr/share/freeswitch/conf/vanilla/. /etc/freeswitch/
    cp /qa-event_socket.conf.xml /etc/freeswitch/autoload_configs/event_socket.conf.xml
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
