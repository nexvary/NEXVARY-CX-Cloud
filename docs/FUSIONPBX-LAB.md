# FusionPBX internal-call acceptance

## Candidate versions

Use a fresh, isolated Debian 13 VM with no production trunks or routes. The
[official installation guide](https://www.fusionpbx.com/download) recommends
Debian 13. Review and pin these source references before running the installer.

| Component | Candidate ref |
| --- | --- |
| Official FusionPBX installer | `681689455dfe20911a041c9ae90aefc6e6d85175` |
| Official FusionPBX 5.6 branch | `9164c2f7bffb415d4fa72ae20bc9a561973799e8` |
| FreeSWITCH [v1.11.4](https://github.com/signalwire/freeswitch/releases/tag/v1.11.4) | `795228ad43ecc8ebd679d15fc189e66de94c252a` |

These references are verified upstream. The combination still requires runtime
acceptance. The official Debian installer's stable source path selects the
moving `v1.11` branch and FusionPBX's moving `5.6` branch by default.

1. Follow the official Debian pre-install prerequisites on the VM and check out
   the installer SHA in `/usr/src/fusionpbx-install.sh` before running
   `debian/install.sh`. Keep `switch_version=1.11`, `switch_branch=stable`,
   `switch_source=true` and `system_branch=5.6` in `debian/resources/config.sh`.
2. In the installer working copy, pin the checkout in the 1.11 case of
   `debian/resources/switch/source-release.sh` to the FreeSWITCH SHA above.
   Pin the clone in `debian/resources/fusionpbx.sh` to the FusionPBX SHA
   immediately after cloning and before setup runs. Make the installer abort
   the installation on either failed checkout, and confirm both source checkout
   SHAs before accepting the result. Preserve the local installer diff and
   record dependency and package versions alongside the results.
3. Run `fs_cli -x version`, `fs_cli -x 'module_exists mod_sofia'` and
   `fs_cli -x 'module_exists mod_event_socket'`. Require the pinned version
   and both modules to report `true`. If 1.11.4 fails, capture the blocking step
   and validate a documented stable 1.11.x candidate before changing the pin.

## Nexvary configuration

Deploy the existing Laravel app, database and assets independently. Provision
FreeSWITCH only through FusionPBX on this lab. The Nexvary bundled PBX installer
and bundled Compose FreeSWITCH service are intended for the local-management
deployment. Do not run them against the lab PBX or mount its configuration
directory into Nexvary.

Set the following in the protected Nexvary environment and refresh the Laravel
configuration cache and worker processes.

- `FREESWITCH_FUSIONPBX=true`
- `FREESWITCH_HOST=127.0.0.1` when on the same VM or using a local tunnel
- `FREESWITCH_ESL_PORT=8021` or the tunnel's local port
- `FREESWITCH_ESL_PASSWORD` set to the same generated secret as FusionPBX's ESL
  configuration. Coordinate changes with FusionPBX so its own ESL access works.

Bind ESL to loopback, or use isolated private networking with an ESL ACL and
firewall allowing only the required private sources. Use an encrypted tunnel
when crossing hosts without a trusted private link. ESL has privileged access
to the engine. Protect the app environment and config cache, keep debug off,
and verify the socket is inaccessible from outside the lab.

Use **Settings → Server** as a verified Nexvary administrator. The page checks
ESL roughly every five seconds while visible. For a successful connection with
a failed count query, the count displays as unavailable.

## Acceptance evidence

1. In FusionPBX, create extensions 1001 and 1002 in the same lab domain and
   register two softphones with distinct credentials. Confirm both in
   **Status → Registrations** or `fs_cli -x 'sofia status profile internal reg'`.
2. Dial 1002 from 1001. Answer and keep the call up while each side speaks or
   plays a distinguishable test tone. Verify audio at both endpoints. Capture
   SIP answer/ACK and BYE/200 OK and collect RTP stream statistics for the SDP
   addresses and ports on both legs. With Wireshark RTP Streams or client
   media statistics, record nonzero sent and received packet counts per leg.
   Keep captures protected and publish only redacted summaries.
3. Compare the Nexvary page with `fs_cli -x 'show calls count'` before,
   during and after the bridged call. Expect 0 → 1 → 0 on a quiet switch.
   Capture both sources at corresponding times. Use the same switch for both.
4. Stop only the Nexvary application service and its workers. Place and answer
   a fresh internal call and verify bidirectional audio again. Start Nexvary
   and confirm the page resumes reading live status.
5. With a valid admin session and CSRF token, submit a direct restart or PBX
   write request and require HTTP 403. Verify an authenticated non-admin
   gets HTTP 403 for `/settings/server` and the public legacy status API is
   blocked in this mode. Disconnect only the Nexvary ESL path and check that
   the UI reports ESL unreachable and the count unavailable. Restore ESL and
   verify recovery.

The acceptance gate for Telnyx is a real call with bidirectional RTP,
matching live Nexvary status, and a second successful call with Nexvary stopped.

## Open release blockers

- [Bundled Docker integration](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45835913025)
  failed at **Start Compose stack**. Run FusionPBX acceptance on the independent
  VM described above.
- [Composer audit](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45835853313/job/139964387189)
  failed independently of the Docker workflow. Resolve the audit findings and
  rerun the gate before release.

## External calling comparison

| Capability | FusionPBX | Nexvary in FusionPBX mode |
| --- | --- | --- |
| SIP gateways | Accounts → Gateways manages provider and registration settings | Settings → Integrations points administrators to FusionPBX. The legacy trunk editor is blocked. |
| Outbound calls | Dialplan → Outbound Routes chooses a gateway and dialing rules | Independent test gateway and outbound route still require live acceptance. |
| Inbound calls | Dialplan → Destinations maps a DID to an extension, IVR or another destination | Independent test DID and inbound route still require live acceptance. |
| Extensions and registration | Accounts → Extensions and Status → Registrations | Android pairs with the Nexvary API. SIP setup and real registration still require verification. |
| IVR, queues, ring groups, voicemail | Managed by FusionPBX | Existing local Nexvary editors are blocked in FusionPBX mode. End-to-end workflows remain to be verified. |

Set the optional `FUSIONPBX_ADMIN_URL` to a trusted HTTPS administration URL
reachable by the operator. The URL is shared only on the administrator's
Integrations page. Keep FusionPBX administration restricted to authorized
operators and retain the existing private ESL deployment.
