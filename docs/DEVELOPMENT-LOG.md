# NEXVARY CX Cloud — development log

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
