# NEXVARY CX Cloud — Feature Parity Matrix

Track each CX milestone individually. Status: **Verified** | **Partial** | **Missing** | **Blocked**

Routes, static screens, mock data, disabled draft records and README claims do not establish Verified status.
Update this file in the same commit as development work. Do not infer prior completion without evidence.

---

## CX01 — Installation

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Reproducible Debian/Ubuntu installer | Partial | `INSTALL-NEXVARY.sh` exists; CI installer tests pass (SiteOS installer gate) |
| Existing-install detection | Partial | `installer/lib/detect.sh`: `nx_detect_existing_install()` scans candidate dirs, reads .nexvary-version; `nx_check_upgrade_safety()` guards version mismatch. |
| Safe upgrades | Partial | `nx_check_upgrade_safety()` prompts/guards on --upgrade-from; version mismatch check; full backup before migration. |
| HTTPS domain access | Partial | Web wizard step checks domain |
| Setup wizard | Partial | Laravel and Inertia wizard exercised by `scripts/qa-installation.sh` against PostgreSQL and Redis. See the development log for run evidence. |
| Initial administrator creation | Partial | Wizard creates admin; CI-tested |
| Database / Redis / ESL connectivity checks | Partial | Live PostgreSQL and Redis verified in the wizard. Authenticated ESL and local SIP verified in isolated QA. The rebuilt Docker stack also reached healthy FreeSWITCH. |
| Certificate reuse | Partial | nx_configure_https_existing_cert() in installer/lib/nginx.sh; auto-detected on re-run; --reuse-cert flag. No test evidence. |
| Rollback | Partial | `installer/lib/detect.sh`: `nx_backup_config_before_upgrade()` backs up .env + freeswitch-conf to /var/backups/nexvary/pre-upgrade-{ts}/; `nx_rollback_config()` restores them. `nexvary-install.sh` calls backup before Phase 4. |
| Migration and recovery | Partial | nx_run_migrations() rolls back and surfaces backup path on failure. nx_recover_install() lists pre-upgrade backups at installer start. No OS-level recovery test. |
| Clean install test | Partial | CI covers browser setup, sign-in and an application restart. Docker Compose startup is a separate gate. See the development log for run evidence. |
| Failed-install recovery test | Partial | Browser QA passed recovery from rejected PostgreSQL credentials and Redis connection failure. OS-level failed-install rollback remains unverified. |

**Next action:** Live upgrade test from a prior installed version; certificate reuse; OS-level failed-install rollback test.

---

## CX02 — Core PBX

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Extensions | Partial | CRUD UI + backend; disabled records only; no live provisioning |
| SIP credentials | Partial | Stored encrypted; not activated in FreeSWITCH |
| Registration | Partial | Docker run 45905082801 reached healthy Sofia but sent REGISTER to loopback instead of the bound SIP address. Run 45906367631 confirmed transport recovery and exposed a 401 on the authenticated retry. CI now installs the tenant XML manifests required by stock FreeSWITCH. Run 45906906767 verified registration and wrong-password rejection. Run 45908854069 verified call setup and teardown and bidirectional RTP. Cross-tenant credential isolation still requires a passing Docker run on PR #1. |
| Gateways / trunks | Partial | `PBX/Trunks` UI + backend; 25 provider presets (CX13); disabled records |
| Provider presets (Telnyx + generic SIP) | Partial | 25 documented presets; not live-tested |
| DIDs | Partial | `PBX/DIDs` UI; assignment not provisioned |
| Inbound / outbound routing | Partial | `PBX/Routes` UI + backend; `Routes/Form.tsx` added |
| Caller ID / privacy | Partial | `NumberTranslationService`: `privacy_enabled` sets `privacy=yes`, `sip_h_Privacy=id`, `effective_caller_id_number=anonymous`; `caller_id_override` sets specific caller ID. 4 Pest tests in `NumberTranslationTest`. |
| Number translation | Partial | `NumberTranslationService` renders continue=true dialplan XML to `dialplan/{domain}/nexvary-translate-{id}.xml`. CRUD via `NumberTranslationController`. Direction: inbound/outbound/both. 4 Pest tests in `NumberTranslationTest`. |
| Codecs | Partial | `SipProfile::defaultCodecs()` returns [PCMU, PCMA, G722, G729, opus]; stored per-profile as JSON; rendered in `inbound-codec-prefs` / `outbound-codec-prefs` params of sofia profile XML. |
| SIP profiles | Partial | `SipProfileService` + `FusionPbxConfigService::writeSipProfileConfig()` write sofia profile XML to `sip_profiles/nexvary-{type}-{id}.xml`. `activate()` / `deactivate()` / `getStatus()` via ESL. `SipProfileController` CRUD. 4 Pest tests in `SipProfileLifecycleTest`. |
| NAT | Partial | `SipProfile` model: `nat_enabled`, `ext_sip_ip`, `ext_rtp_ip`, `aggressive_nat_detection`. Rendered in sofia profile XML as `NDLB-force-rport` and `aggressive-nat-detection` params. |
| TLS / SRTP | Partial | `SipProfile` model: `tls_enabled`, `tls_port`, `srtp_mode` (disabled/optional/mandatory). Sofia XML includes `tls`, `tls-sip-port`, `tls-cert-dir`, `tls-version`, `rtp-secure-media` params when configured. |
| Device provisioning | Partial | `DeviceProvision` model (UUID, BelongsToTenant, hashed token, device_type). `DeviceProvisionService` renders vendor-specific XML (Yealink, Polycom, Grandstream, Cisco, Snom, generic). Unauthenticated GET /provision/{token} endpoint (rate-limited 20/min). Token regeneration. 5 Pest tests: create, 404 on unknown token, XML served, disabled record 404, cross-tenant deny. |
| Dialplan editor | Partial | `DialplanRule` model (UUID, BelongsToTenant, conditions/actions JSON, priority, continue). `DialplanRuleService` renders continue=true/false FreeSWITCH dialplan XML to `dialplan/{domain}/nexvary-rule-{id}.xml`. CRUD via `DialplanRuleController`. ESL dialplan reload on change. 4 Pest tests: create, invalid regex, no actions, cross-tenant deny. |
| Validation | Partial | `ConfigValidationService::validateSipProfile()`: port range, IP/hostname, codec allowlist, srtp_mode, rtp_timeout. `validateDialplanRule()`: condition field/regex, action application. `validateXml()`: DOMDocument well-formedness. Used by DialplanRuleController before writing. |
| Configuration activation and rollback | **Partial** | `activate`: writes config → ESL reload (bool-checked) → enable; rolls back config file on ESL failure. `deactivate`: best-effort ESL killgw → removes config file → disables. Tenant isolation enforced via model binding (TenantScope) + explicit tenant_id check. 7 tests in TrunkLifecycleTest (REGED, ESL-down rollback, UNKNOWN status, killgw, ESL-down deactivate, cross-tenant 404). |

**Next action:** Live FreeSWITCH integration test in Docker CI; add registered state polling for TRYING→REGED transitions.

---

## CX03 — Call Features

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Transfer | Partial | `ActiveCallService::blindTransfer/attendedTransfer` via `uuid_transfer`/`uuid_bridge` ESL. `ActiveCallController` POST /{uuid}/transfer. 2 Pest tests. |
| Hold / resume | Partial | `ActiveCallService::hold/resume` via `uuid_hold`/`uuid_hold off` ESL. MoH class applied on hold. `ActiveCallController` POST/DELETE /{uuid}/hold. 2 Pest tests. |
| Call waiting | Partial | FreeSWITCH handles call-waiting by default; `uuid_hold` suspends active leg. No dedicated NEXVARY UI yet. |
| Parking | Partial | `ActiveCallService::park` via `valet_park` ESL. `ActiveCallController` POST /{uuid}/park. Pest test: parks call, returns slot. |
| Pickup | Partial | `ActiveCallService::pickup` via `valet_park {retrieveUuid} {slot}` ESL. `ActiveCallController` POST /parking/{slot}/pickup. |
| DND | Partial | `ExtensionService::setDnd()` writes `dnd_enabled` flag. FreeSWITCH dialplan reads `${dnd_enabled}`. Pest test. |
| Forwarding / Follow Me | Partial | `ExtensionService::setForward()` writes `forward_enabled`/`forward_destination`. Pest tests (enable, null to disable). |
| Blacklist | Partial | CallBlacklist model + BlacklistService writes dialplan XML (CALL_REJECTED). Exact/prefix/regex patterns, inbound/outbound/both. 4 Pest tests. CRUD UI. |
| Business hours / holidays | Partial | BusinessHours model (weekly schedule JSON, holidays, timezone). BusinessHoursService writes time-condition dialplan XML. 4 Pest tests. CRUD UI. |
| Day / night mode | Partial | DayNightMode model with one-click toggle (day↔night). Destinations per mode. UI toggle on Business Hours page. |
| Dial by name | Missing | — |
| Hot desking | Missing | — |
| Feature codes | Partial | Static display page of standard FreeSWITCH codes (*2 pickup, *3 DND, *4 forward, *52 voicemail, *68 park, *88 retrieve, **transfer, etc.). |
| Music on hold | Partial | MohClass model (local files or stream URL, shuffle). MohService + renderConfig() for local_stream.conf. 3 Pest tests. Management UI. |
| Announcements and audio management | Missing | — |

**Next action:** Implement after CX02 live provisioning is established.

---

## CX04 — Contact Center

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| IVR | Partial | `IvrProvisioningService` writes `autoload_configs/ivr.conf.d/nexvary-ivr-{id}.xml`. Wired into `IvrController` store/update/destroy. 2 Pest tests. |
| Ring groups | Partial | `RingGroupProvisioningService` writes dialplan XML with bridge application (simultaneous/sequential). Wired into `RingGroupController`. 3 Pest tests. |
| Queues | Partial | `QueueProvisioningService` writes `autoload_configs/fifo.conf.d/` XML + dialplan entry. Wired into `CallQueueController`. |
| Agents | Partial | `CallAgent` model (tenant-scoped, extension FK, skill level, status). `CallAgentController` CRUD + status toggle. Agents/Index.tsx + Form.tsx. 4 Pest tests. |
| Distribution strategies | Partial | `call_queues.strategy` column (existing). Ring group strategy (simultaneous/sequential/ordered) used by `RingGroupProvisioningService`. |
| Operator panel / live calls | Partial | `CallCenter/Live.tsx` page exists; mock data only |
| Authorized hangup | Missing | Not implemented |
| CDR | Partial | `CallCenter/Records.tsx` page; no live data |
| Consent-aware recording | Partial | `CallCenter/Recordings.tsx` page; no live recording |
| Recording access and retention | Missing | Not implemented |
| Audio / video conferences | Missing | Not implemented |
| Voicemail | Partial | `PBX/Voicemail/Index.tsx`; no live delivery |
| Voicemail-to-email | Missing | Not implemented |

---

## CX05 — Fax

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Fax extensions | Missing | — |
| PDF send / receive | Missing | — |
| Inbox / outbox | Missing | — |
| Delivery status and retry | Missing | — |
| Fax-to-email | Missing | — |
| T.38 and G.711 fallback | Missing | — |
| Authorized downloads | Missing | — |
| Interoperability tests | Missing | — |

---

## CX06 — Messaging and Email

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| SMS / MMS provider integration | Partial | `SMS/Index.tsx` page; Telnyx SMS service skeleton |
| Inbound signature-verified webhooks | Missing | Not implemented |
| Outbound messaging | Missing | Not implemented |
| Conversations UI | Partial | `SMS/Index.tsx` stub |
| Delivery status | Missing | Not implemented |
| Consent and rate limits | Missing | Not implemented |
| SMTP / IMAP integration | Missing | Service skeleton only |
| Email management | Missing | Not implemented |

---

## CX07 — Administration

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Tenants / domains | Partial | Multi-tenant model; admin UI for tenants |
| Users / groups | Partial | RBAC with admin/superadmin/agent; `Settings/` pages |
| Granular RBAC / ACLs | Partial | Spatie roles; not all routes gate-tested |
| MFA / recovery | Partial | 2FA with pragmarx/google2fa; recovery codes page exists |
| Device management | Missing | — |
| System variables | Missing | — |
| FreeSWITCH modules | Missing | — |
| Menu configuration | Missing | — |
| Service health | Partial | Health endpoint; no dashboard |
| Authorized service restart | Missing | — |
| Audit | Partial | spatie/laravel-activitylog wired |
| Logs | Missing | No log viewer |
| Backup / restore | Partial | `Settings/Backup.tsx` stub |
| Updates | Missing | — |
| Monitoring / alerts | Missing | — |
| Fraud controls / destination restrictions | Missing | `Admin/CallFreeze.tsx` exists; spending limits not implemented |
| Emergency call freeze | Partial | `Admin/CallFreeze.tsx` page exists |

---

## CX08 — CRM and Billing

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Contacts | Partial | `CRM/Contacts/Index.tsx`; basic CRUD |
| Tickets | Partial | `CRM/Tickets/Index.tsx`; basic CRUD |
| Call / message linkage | Missing | — |
| Rate tables | Partial | `Billing/Rates/Index.tsx`; no active rating |
| Billing / balances / invoices | Partial | `Billing/Accounts` and `Billing/Invoices` pages; mock data |
| Usage reports | Partial | `Billing/Reports.tsx`; mock data |
| Reconciliation | Missing | — |
| Configurable plans | Missing | — |

---

## CX09 — WebRTC and Optional Voice AI

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Browser calling | Partial | WebRTC bridge exists (`rtc-bridge/`); no live call test |
| Secure signaling / media | Partial | Node.js WebSocket bridge; TLS not verified |
| Opt-in transcription / translation | Missing | AI service skeleton only |
| Voice assistance and analytics | Missing | — |
| Consent / retention controls | Missing | — |
| Provider abstraction | Partial | Pluggable adapter pattern; not implemented |
| Core PBX without AI | Partial | Architecture supports; not live-tested |

---

## CX10 — Android Full Administration

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Native Android app | Missing | No Android project in this repository |
| Versioned mobile API | Missing | — |
| All web admin functions via mobile | Missing | — |
| Multi-server profiles | Missing | — |
| QR pairing | Missing | — |
| Secure manual connection | Missing | — |
| Android Keystore token storage | Missing | — |
| Arabic RTL / English LTR | Missing | — |
| Notifications | Missing | — |

---

## CX11 — Visual PBX Builder

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Web graphical workflow editor | Missing | — |
| Android workflow editor | Missing | — |
| Reusable templates | Missing | — |
| Trigger / condition / time rules | Missing | — |
| IVR / queue / destination nodes | Missing | — |
| Preview / simulation | Missing | — |
| Schema validation / loop detection | Missing | — |
| Revision history | Missing | — |
| Safe publish and rollback | Missing | — |
| Compile to PBX configuration | Missing | — |

---

## CX12 — Product UX

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| Glossy deep black / metallic silver | Partial | Nexvary CSS design system; partial implementation |
| Meaningful multicolor icons | Missing | lucide-react (monochrome); multicolor SVG icons not implemented |
| Neon green / red status accents | Missing | Not verified |
| Accessible contrast | Missing | Not audited |
| Responsive desktop / mobile | Partial | Actual dashboard cards verified without clipped text at 390×844 and 1440×900, in Arabic and English. Broader screen coverage remains partial. |
| Bilingual Arabic / English RTL / LTR | Partial | i18next; layout not fully RTL-verified |
| Guided setup | Partial | Install wizard |
| Contextual help | Partial | `Info/Help.tsx` stub |
| About System page | Partial | `Info/About.tsx` page |
| About Developer page | Partial | `Info/Developer.tsx` page |
| Contact Developer function | Partial | `Info/Developer.tsx` page; form without working transport |
| Day / Night UI themes | Partial | Browser scenarios passed the localized web toggle, card layout in both themes, and theme persistence after reload. Full contrast and Android theme parity remain unverified. |
| Real operational data in dashboard | Missing | Mock data throughout |
| Empty / loading / error states | Missing | Not consistently implemented |

**Next action:** Verify theme contrast and replace monochrome icons with multicolor SVG or licensed assets.

---

## CX13 — Provider Ecosystem and Advanced Operations

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| 25 documented provider presets | **Verified** | All 25 presets in `SipProviderPresets.php`; `documented()` returns 25; tested in `SipProviderPresetsTest.php` |
| Generic custom SIP | **Verified** | `generic` preset covers custom SIP, credential/IP, all transports |
| Encrypted credentials | Partial | defuse/php-encryption used for SIP credentials; presets store no credentials |
| Provider-specific capabilities documented | Partial | Each preset has `docs_url`, `instructions` EN/AR, `auth_modes`; not live-tested |
| Gateway Center (health, reconnect, stats) | Missing | — |
| Call Quality Analyzer | Missing | — |
| SIP Diagnostic Center | Missing | — |
| Fraud Protection Center | Partial | `Admin/CallFreeze.tsx`; full fraud controls missing |
| Legacy PBX Migration Wizard | Missing | — |
| Disaster Recovery Center | Partial | `Settings/Backup.tsx` stub |
| SIP QR Provisioning | Missing | — |
| Multi-Server Control Center | Missing | — |
| Nexvary Update Center | Missing | — |
| Nexvary Academy | Missing | — |
| Web / mobile admin parity for CX13 | Missing | — |

---

## Independent Branding

| Item | Status | Evidence / Notes |
|------|--------|-----------------|
| NEXVARY CX Cloud branding in UI | **Verified** | No competitor PBX names in UI code or navigation |
| README product table | **Verified** | Changed "FusionPBX-compatible XML" → "FreeSWITCH XML" |
| Provider preset docs_url (branding) | **Verified** | FusionPBX-specific URLs removed from Telnyx and VoiceTel presets |
| FusionPBX attribution preserved | **Verified** | `docs/OPEN_SOURCE_COMPONENTS.md` legal attribution unchanged |
| Internal config service comments | Partial | `FusionPbxConfigService.php` retains technical reference comments; not user-facing |

---

*Last updated: 2026-11-27 (CX03 active-call: hold/resume/transfer/park/pickup/DND/forwarding; CX04: IVR/ring-group/queue live provisioning, call agents) | Branch: feature/stage-1-foundation*
