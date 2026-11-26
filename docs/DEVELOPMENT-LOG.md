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

## 2026-11-26 — CX13 and independent identity
- Approved 25-provider target and advanced operations modules; independent Nexvary branding required.
- Status: specification updated only; implementation and live interoperability not verified.
- Next: audit existing references, preserve required legal notices, reconcile feature matrix and implement in dependency order.
