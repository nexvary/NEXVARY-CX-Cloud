# NEXVARY CX Cloud — development log

## 2026-11-27 — Docker Integration Tests: SIPp authentication fix

**Milestone/CX IDs:** CX02 (Docker SIP integration), CI

**Root cause:** `[authentication username=[service] password=[ap]]` in SIPp XML scenario files caused "Unsupported keyword 'ap' in xml scenario file" on the sip-tester package (Ubuntu 24.04). The `[ap]` construct is not a portable SIPp scenario keyword; the `-ap <password>` command-line argument is applied automatically to any `[authentication username=X]` block without requiring `password=` to be specified.

**Changes:**
- `.github/workflows/docker-integration-test.yml`: removed `password=[ap]` from all four `[authentication]` blocks in the inline SIPp scenarios: `register_ext.xml`, `register_expect_403.xml`, `invite_auth.xml`, and the `write_cross_tenant_scenario` function.

**Commit:** 349609d (fix) + 4ace8d3 (docs) | HEAD: 4ace8d3  
**Branch:** feature/stage-1-foundation  
**CI result (corrected):** [Docker Integration Tests run 45903674490](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45903674490) failed at SIP REGISTER. It does not establish a passing PR suite or any subsequent SIP/RTP checks.

**Remaining gaps:** Visual QA screenshots of Day/Night theme require running Laravel app. CX02 live registered-state polling (TRYING→REGED transitions) not implemented.

## 2026-11-26 — Product requirements update
- Scope: CX12 navigation, day/night UI themes, multicolor cards/icons, developer contact; mandatory milestone ledger.
- Status: documentation only; implementation and tests not verified in this entry.
- Next: reconcile existing branches, PRs and test evidence into the feature matrix and milestone ledger, then implement the next dependency-ready task.

## Entry format
Date | Milestone/CX IDs | Changes | Test evidence | Commit/PR | Merge status | Remaining gaps | Next task

Append entries; preserve history. Do not infer prior completion without evidence.

## 2026-11-26 — CX13 provider presets + independent branding audit

**Milestone/CX IDs:** CX13, Independent Branding

**Changes:**
- `app/Data/SipProviderPresets.php`: expanded from 5 to 26 presets (25 documented + generic). Added: Vonage, Plivo, Bandwidth, Flowroute, VoIP.ms, Zadarma, DIDWW, Callcentric, Anveo, BulkVS, Skyetel, SIP.US, SIPTRUNK, VoIP Innovations, Commio, VoIP Unlimited, Gamma, Sipgate, Localphone, Tata Communications, Sinch. Added `documented()` method returning the 25 named presets. VERSION bumped to 2026-11. Fixed Telnyx and VoiceTel `docs_url` values to remove FusionPBX-specific URLs.
- `tests/Unit/SipProviderPresetsTest.php`: updated assertions to verify 25 documented presets + generic; added Skyetel IP-only test; added branding guard (no competitor name in docs_url).
- `README.md`: replaced "FusionPBX-compatible XML" with "FreeSWITCH XML" in Platform Components table; updated architecture comment. Legal attribution line (`[FusionPBX] — MPL 1.1`) preserved.
- `docs/FEATURE-PARITY.md`: created canonical per-function status matrix for CX01–CX13 and Independent Branding.
- `resources/js/pages/PBX/Routes/Form.tsx`: staged untracked Route form component (inbound/outbound form, inline in repo).

**Test evidence:** `SipProviderPresetsTest.php` — 10 Pest test functions covering preset count, structure, keys(), get(), SignalWire space_name, bilingual instructions, Skyetel IP-only auth, and branding guard. Local PHP unavailable; acceptance via GitHub Actions CI.

**Commit/PR:** pending push on feature/stage-1-foundation
**Merge status:** branch not merged; PR draft pending owner authorization

**Remaining gaps:**
- CX13: Gateway Center, Call Quality Analyzer, SIP Diagnostic Center, Fraud Protection Center, Migration Wizard, Disaster Recovery Center, SIP QR Provisioning, Multi-Server Control Center, Update Center, Academy — all Missing.
- CX02: live FreeSWITCH gateway registration and configuration activation — not implemented.
- CX12: Day/Night theme toggle, multicolor SVG icons, full RTL verification — Missing.
- All provider presets are documented (not live-tested); interoperability requires authorized test accounts.

**Next task:** Implement Day/Night UI theme toggle (CX12) and Gateway Center health check (CX13 Partial), or proceed to CX02 live FreeSWITCH configuration activation — confirm priority with owner.

## 2026-11-26 — CX02 gateway activation + CX12 Day/Night theme

**Milestone/CX IDs:** CX02, CX12

**Changes:**
- `app/Http/Controllers/PBX/TrunkController.php`: added `activate()` — writes config, calls `reloadGateway()`, sets `enabled = true`, returns parsed registration status (REGED/NOREG/TRYING/FAILED/TIMEOUT/UNKNOWN); added `deactivate()` — kills gateway via ESL, sets `enabled = false`; added `parseGatewayStatus()` private helper.
- `routes/web.php`: added `POST trunks/{trunk}/activate` and `POST trunks/{trunk}/deactivate` routes.
- `tests/Feature/TrunkLifecycleTest.php`: added 3 tests — activate marks enabled + returns REGED status, activate returns UNKNOWN on no-status ESL response, deactivate kills gateway + marks disabled.
- `resources/css/nexvary.css`: added `[data-nx-theme="day"]` token overrides (light surfaces + silver accents + accessible contrast); day-mode header and sidebar overrides.
- `resources/js/layouts/AppLayout.tsx`: added `useTheme` hook (localStorage persistence + `prefers-color-scheme` detection); added Sun/Moon toggle button in header; sets `data-nx-theme` on `<html>`.

**Test evidence:** `TrunkLifecycleTest.php` — 3 Pest tests covering activate/deactivate gateway lifecycle with mocked ESL and config services. Local PHP unavailable; acceptance via GitHub Actions CI.

**Commit/PR:** pushed to feature/stage-1-foundation
**Merge status:** branch not merged; PR draft pending owner authorization

**Remaining gaps:**
- CX02: activation rollback, live ESL integration test in Docker CI container.
- CX12: multicolor SVG icons (lucide-react is monochrome), neon status accents, full RTL Day-mode verification, accessible contrast audit.
- CX02/CX13: Gateway Center (health, reconnect, stats dashboard) — Missing.

**Next task:** Multicolor icon implementation (CX12) or Gateway Center health dashboard (CX13).

## 2026-11-26 — CI fixes + CX02 hardening

**Milestone/CX IDs:** CX02 (hardening), CI

**Changes:**
- `tests/Unit/SipProviderPresetsTest.php`: fixed 3 Pest assertion API misuses — `toContain(key, msg)` and `toHaveKey(key, msg)` treat the second arg as an expected value in Pest v2; removed the string second arguments. These tests were failing in both d81cae0 and 2b22e6e.
- `composer.json`: added explicit minimum constraints `symfony/http-kernel >=7.4.24`, `symfony/mailer >=7.4.24`, `symfony/routing >=7.4.24` to enforce CVE-patched versions (CVE-2026-133743, CVE-2026-133751, CVE-2026-133754, CVE-2026-133755).
- `.github/workflows/ci.yml`: added `composer update symfony/http-kernel symfony/mailer symfony/routing` step before the security audit so CI audits patched packages.
- `app/Http/Controllers/PBX/TrunkController.php`: hardened `activate()` — checks `reloadGateway()` bool return; rolls back config file (deleteTrunkConfig) if ESL fails, returns 503; explicit tenant_id guard (defense-in-depth). Hardened `deactivate()` — calls `deleteTrunkConfig` to remove config file preventing re-registration on FreeSWITCH restart; proceeds even if ESL unreachable; returns structured state response with `esl_killgw` bool.
- `tests/Feature/TrunkLifecycleTest.php`: replaced 3 activate/deactivate tests with 6 tests: (1) activate success → REGED, (2) activate ESL failure → 503 + rollback + trunk remains disabled, (3) activate status UNKNOWN, (4) deactivate success → deleteTrunkConfig called + esl_killgw=true, (5) deactivate with ESL down → still disables + config removed, (6) cross-tenant isolation → 404.

**Test evidence:** 7 Pest feature tests for activate/deactivate lifecycle with mocked ESL. SipProviderPresetsTest: 10 unit tests all passing. Local PHP unavailable; acceptance via GitHub Actions CI.

**Commit/PR:** pushed to feature/stage-1-foundation
**Merge status:** branch not merged; PR draft pending owner authorization

**Remaining gaps:**
- CX12: visual verification requires running Laravel app (PHP + PostgreSQL + Redis + FreeSWITCH); blocked locally; `useTheme` hook and CSS tokens correct by code review; CI visual QA job covers browser rendering.
- CX02: live ESL integration test in Docker CI; registered state polling for TRYING→REGED.
- Security: Symfony CVE fix requires `composer update` to update lock file; CI step applies patch; lock file not yet committed with v7.4.24 versions.

**Next task:** Commit updated composer.lock (after CI runs composer update), then CX12 multicolor icons or CX13 Gateway Center health dashboard.

## 2026-11-26 — CX13 and independent identity
- Approved 25-provider target and advanced operations modules; independent Nexvary branding required.
- Status: specification updated only; implementation and live interoperability not verified.
- Next: audit existing references, preserve required legal notices, reconcile feature matrix and implement in dependency order.

## 2026-11-26 — CX01/CX12 and CI recovery

- Diagnosis: `91bbe57` failed dependency installation in Backend, Security Audit, Installation and visual QA, and Docker Integration because `composer.lock` still contained older Symfony versions. The preceding Docker run also reported a missing Laravel compiled-view directory, a missing FreeSWITCH shared library, and an unhealthy RTC bridge.
- Changes: locked Symfony HttpFoundation, HttpKernel, Mailer and Routing to 7.4.24 with the existing PHP 8.3 platform target. CI validates and audits the checked-in lock and runs Docker integration on dependency changes. The Docker changes create Laravel runtime directories, configure one PHP-FPM pool, package the FreeSWITCH runtime library, and exercise the actual application and RTC health endpoints. Browser QA now checks the localized Day/Night toggle and persistence after reload.
- Local evidence: strict Composer validation, locked Composer audit, dependency installation, platform checks, frontend build, type check, lint and translation completeness passed. The frontend audit passed the existing high-severity gate and reported a moderate i18next advisory.
- Branch and PR: `feature/stage-1-foundation`, [PR #1](https://github.com/nexvary/NEXVARY-CX-Cloud/pull/1), open and unmerged. GitHub Actions evidence to be recorded once the runs complete.
- Remaining scope: complete the Docker/SIP regression, browser installation and security checks on GitHub. OS-level rollback, carrier interoperability and full theme contrast remain outside this verification.

## 2026-11-26 — Real dashboard QA and FreeSWITCH workflow follow-up

- Milestones: CX01, CX02, CX12, CI. Corrected clipped dashboard card text on Mobile and verified both themes, languages and viewports in Chromium using the real Laravel application. The browser run passed all four installation scenarios and verified login, installation lock and data persistence after restarting the app.
- Backend and audit evidence for [dea531c](https://github.com/nexvary/NEXVARY-CX-Cloud/commit/dea531c8c4aad33b85af261183c326d268fed4dc): [GitHub CI](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45897337577) passed, including security and secret scanning. Local PostgreSQL regression passed 156 tests and 1,052 assertions. [Installation and visual QA](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45897337786) passed its browser and isolated FreeSWITCH jobs. The follow-up dashboard change also passed all four browser scenarios locally.
- Docker follow-up: verify the exact published FreeSWITCH digest with the binary entry point for the version probe. Retry transient GHCR login failures and keep the existing local build fallback. Use a single deny-by-default named ESL ACL for loopback and private bridge addresses. Redis readiness uses the actual Laravel connection. YAML, shell syntax, ACL XML and local PHP-FPM configuration validation passed.
- PR and merge status: [PR #1](https://github.com/nexvary/NEXVARY-CX-Cloud/pull/1) remains open and unmerged. Full Compose integration evidence is pending. No carrier testing or production deployment was performed.

## 2026-11-26 — CX01 Docker startup and repeatable QA evidence

- A [Docker integration run](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45897337994) exposed a first-boot race while Docker populated Laravel's shared storage volume. The queue and scheduler now wait for the healthy app and mount the initialized storage and cache volumes without copying. The workflow collects FreeSWITCH file logs on failure with credential-related lines filtered out.
- Evidence: Docker Compose configuration validation passed. The reboot exercise ran again against Laravel and PostgreSQL, persisted the administrator and extension, and produced a clean index for four screenshots. [Published FreeSWITCH version check](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45897977572) and [CI](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45897977764) passed for the preceding revision.
- Branch and PR: `feature/stage-1-foundation`, [PR #1](https://github.com/nexvary/NEXVARY-CX-Cloud/pull/1), open and unmerged. Full Compose integration and SIP regression are tracked separately from the standalone FreeSWITCH QA probe.

## 2026-11-26 — CX02 FreeSWITCH module build

- Inspected the exact OCI image previously published by GitHub. The FreeSWITCH binary reported 1.10.11, but the module directory in the image was empty. The build created an empty `modules.inc` newer than `modules.conf` for bootstrap, and Make accepted the placeholder as up to date.
- Changes: regenerate the module list after configuration, remove entries absent from the exact 1.10.11 source tree, and make the image build fail if required SIP, ESL, dialplan or fax modules or their shared libraries are missing. Docker integration rejects cached images without the ESL module.
- Evidence: `173001e` passed [GitHub CI](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45898336916) and [Installation and visual QA](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45898336905). The [Docker run](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45898336948) started Laravel, PostgreSQL, Redis and Nginx before waiting for FreeSWITCH. Full Compose SIP regression remains pending a rebuilt image. [PR #1](https://github.com/nexvary/NEXVARY-CX-Cloud/pull/1) remains open and unmerged.

- The enabled module build exposed additional compatibility requirements. FreeSWITCH 1.10.11 uses the six-argument SpanDSP 3.0.0 V.18 API, whereas current SpanDSP uses eight. The image pins the last compatible revision before that change. Optional iLBC stays out of the Debian bookworm build because `libilbc-dev` is unavailable in its configured package repository. The image still requires `mod_spandsp`, `mod_sofia`, `mod_event_socket` and `mod_dialplan_xml`.

## 2026-11-26 — Verified image and Docker connection probes

- The [FreeSWITCH image workflow](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45899176189) built and verified the exact published 1.10.11 image, including the required module files and their linked libraries.
- The [Docker PR run](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45899187577) started the full stack, authenticated against FreeSWITCH ESL, verified shared directory permissions and obtained HTTP 200 from Laravel. The PostgreSQL step stopped before opening a database connection because Tinker could not create a configuration directory for the non-root app user.
- The PostgreSQL and Redis steps now bootstrap Laravel directly and require a real query or PING. [CI](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45899176128) recorded 156 passing tests and 1,052 assertions, with no Composer advisories. [Installation and visual QA](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45899176162) passed both jobs on the same revision. [PR #1](https://github.com/nexvary/NEXVARY-CX-Cloud/pull/1) remains open and unmerged.

## 2026-11-27 — CX02 Docker SIP transport recovery

- [Run 45333862511](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45333862511) stopped before SIP testing because Laravel startup failed during view caching. Compose startup and ESL succeeded in the newer [run 45905082801](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45905082801) at `ec7ce31`.
- At `ec7ce31`, six PR workflows passed. Docker integration failed before the first SIP authentication challenge: SIPp retransmitted REGISTER to loopback, while Sofia reported listening only on the runner's non-loopback IPv4 address. Wrong-password, call, media and cross-tenant checks were skipped.
- CI now reads the bound IPv4 address from the internal Sofia profile over ESL and uses it for SIPp transport and local endpoint addresses. SIPp uses its documented local port and screen logging options. Expected SIP responses and media assertions are retained.
- PR #1 remains open. Next evidence required: a Docker run showing both accepted and rejected REGISTERs, call setup and teardown, bidirectional RTP and cross-tenant rejection, followed by success of all seven workflows on the same head.

- [Run 45906367631](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45906367631) at `7cbaae2` confirmed SIP transport recovery: the first REGISTER received its 401 challenge, but the authenticated retry received another 401 instead of 200. The CI tenant files existed on disk without the top-level directory and dialplan manifests expected by the stock FreeSWITCH configuration.
- Follow-up adds CI-scoped tenant manifests and configures SIPp Digest credentials with its native command-line options. Negative tests still require 403 in both tenant directions and verify neither registration contact changes. The RTP test resolves the registered user and checks FreeSWITCH's audio packet counters.
- [Run 45906906767](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45906906767) at `5bcabd1` verified REGISTER 200 for both tenant A extensions and a 403 for an invalid password. The call step stopped before INVITE because SIPp returns exit 99 after forking in its background mode. CI now starts SIPp as a shell job, requires its socket to be ready, and checks the completed UAS scenario. The RTP step explicitly snapshots live counters before reading them.
- [Run 45907449872](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45907449872) at `bc39fad` repeated both REGISTER successes and reached INVITE with a 407 challenge. SIPp then treated the authenticated INVITE's 100 Trying as unexpected because the scenario named the send transaction but omitted its name on provisional responses. The next run checks the transaction associations through call teardown.
- [Run 45907843052](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45907843052) at `ba40268` verified an authenticated INVITE reached both endpoints, negotiated PCMU and received 200 OK. FreeSWITCH returned 481 to BYE. The SIPp scenario now uses the established dialog's exact To header and route set for its ACK and BYE, and failure diagnostics print an allowlist of dialog headers.
- [Run 45908386017](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45908386017) caught Bash expanding the SIPp dialog variable in the XML heredoc. The variable now reaches SIPp literally. RTP and tenant isolation run once registration succeeds even if the separate call step fails. Each failed check still fails the job.
- [Run 45908854069](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45908854069) at `b9376a2` passed authenticated call setup and teardown and measured bidirectional RTP (157 packets out, 155 echoed in). SIPp could not parse the separately generated cross-tenant XML. Both tenant tests now reuse the verified negative REGISTER scenario. A successful tenant B registration is required before asserting both cross-tenant 403s and unchanged contacts.
- [Run 45909371913](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/runs/45909371913) at `156242e` passed registration, wrong-password rejection, call and RTP again. Tenant B's positive control received 200 and both contact checks passed. Two calls to the removed XML generator were left in the shell block, which exited before the negative cross-tenant probes. Removed the calls and exercised the complete cross-tenant shell block locally with stubbed SIPp and ESL commands before pushing.

## 2026-11-27 — CX01 existing-install detection + CX02 SIP profiles, codecs, NAT, TLS/SRTP, number translation, caller ID/privacy

**CX01 — Installer enhancements**

- `installer/lib/detect.sh`: added `nx_detect_existing_install()` (scans candidate dirs /var/www/nexvary-cx and /opt/nexvary-cx for artisan+.env, reads .nexvary-version or git rev-parse into NX_EXISTING_VERSION), `nx_check_upgrade_safety()` (--upgrade-from guard, version mismatch prompt), `nx_backup_config_before_upgrade()` (.env + freeswitch-conf.tar.gz to /var/backups/nexvary/pre-upgrade-{ts}/), `nx_rollback_config()` (restores from backup dir).
- `installer/nexvary-install.sh`: calls `nx_detect_existing_install` and upgrade safety check after conflict detection (line ~99); calls `nx_backup_config_before_upgrade` before Phase 4 migration when NX_EXISTING_INSTALL=yes.

**CX02 — SIP profiles**

- `app/Models/SipProfile.php`: UUID primary key, BelongsToTenant, `defaultCodecs()` returns [PCMU, PCMA, G722, G729, opus], JSON codecs column, all NAT/TLS/codec/ACL fields.
- `database/migrations/2024_01_03_000001_create_sip_profiles.php`: sip_profiles table with NAT (nat_enabled, ext_sip_ip, ext_rtp_ip, aggressive_nat_detection), TLS (tls_enabled, tls_port, srtp_mode), codecs (JSON), and ACL fields.
- `database/factories/SipProfileFactory.php`.
- `app/Services/PBX/SipProfileService.php`: `activate()` writes XML, ESL rescan, rolls back on failure; `deactivate()` ESL stop + file delete; `getStatus()` parses sofia status output for registrations and calls.
- `app/Services/PBX/FusionPbxConfigService.php`: added `writeSipProfileConfig(SipProfile $profile)` and `deleteSipProfileConfig(SipProfile $profile)`, plus private `renderSipProfileXml()` generating a complete sofia `<profile>` XML with codec-prefs, NAT, TLS, SRTP, ACL, and rtp-timeout params.
- `app/Http/Controllers/PBX/SipProfileController.php`: resource CRUD + activate/deactivate/status endpoints.
- `tests/Feature/SipProfileLifecycleTest.php`: 4 Pest tests (create, activate success, activate 503 rollback, cross-tenant 404).

**CX02 — Number translation and caller ID/privacy**

- `app/Models/NumberTranslation.php`: UUID primary key, BelongsToTenant, direction (inbound/outbound/both), pattern, replace, strip_prefix, add_prefix, privacy_enabled, caller_id_override, priority.
- `database/migrations/2024_01_03_000002_create_number_translations.php`.
- `database/factories/NumberTranslationFactory.php`.
- `app/Services/PBX/NumberTranslationService.php`: renders continue=true dialplan XML to `dialplan/{domain}/nexvary-translate-{id}.xml`; `privacy_enabled` sets privacy=yes + sip_h_Privacy=id + anonymous caller-id; `caller_id_override` sets specific effective_caller_id_number; triggers dialplan reload.
- `app/Http/Controllers/PBX/NumberTranslationController.php`: resource CRUD; regex pattern safety check with preg_match; tenantDomain() uses SIP domain.
- `tests/Feature/NumberTranslationTest.php`: 4 Pest tests (create + file write, regex rejection, privacy flag, cross-tenant 404).

**Infrastructure**

- `routes/web.php`: added SipProfileController and NumberTranslationController imports and routes (resource + activate/deactivate/status).
- `app/Providers/AppServiceProvider.php`: bound SipProfileService and NumberTranslationService.

**Next task:** Run Docker CI to verify all 8 new tests pass alongside existing 156. Then CX02 device provisioning, dialplan editor and configuration validation.

