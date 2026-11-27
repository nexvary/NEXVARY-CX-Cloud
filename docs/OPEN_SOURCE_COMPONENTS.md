# NEXVARY CX Cloud — Open-Source Components

This document records all third-party open-source projects whose code has been
studied, adapted, or incorporated into NEXVARY CX Cloud.

---

## FusionPBX

| Field | Value |
|-------|-------|
| Project | FusionPBX |
| Repository | https://github.com/fusionpbx/fusionpbx |
| Reference commit | `49fa864c14d667b55485a0daee7114da124843bc` (master, studied 2026-11-23) |
| License | Mozilla Public License 1.1 (MPL 1.1) |
| Copyright | © 2008–2024 Mark J Crane and contributors |

### License obligations (MPL 1.1)

The MPL 1.1 is a file-level copyleft license. Its primary obligations are:

1. **Source availability**: If you distribute modified MPL-covered files, the source of those files must be made available under MPL 1.1.
2. **Copyright notice preservation**: Original copyright and license notices must be retained in any modified files.
3. **No re-licensing**: MPL-covered code cannot be re-licensed under a more restrictive licence.
4. **Larger work combination**: MPL code may be combined with proprietary code in a "Larger Work" provided the MPL-covered files remain available under MPL 1.1.

> **Legal review note**: NEXVARY CX Cloud is a commercial platform. Before distributing any file that directly incorporates MPL-covered FusionPBX source, obtain a legal review to confirm the Larger Work provisions are correctly applied.

### Files studied (not copied)

The following FusionPBX source files were **read for reference only** to understand proven FreeSWITCH configuration patterns. No code was copied line-for-line. Configuration patterns were independently re-implemented.

| FusionPBX file | Purpose studied |
|---------------|-----------------|
| `app/extensions/resources/functions.php` | Per-domain user directory XML structure (`directory/{domain}/{ext}.xml`), `user_context` variable usage |
| `app/dialplans/resources/functions.php` | Per-domain dialplan context naming (context == domain name), inbound transfer target `{dest} XML {domain}` |
| `app/gateways/resources/functions.php` | Gateway XML structure for `sip_profiles/external/` |

### Key patterns adapted

The following FreeSWITCH multi-tenancy patterns were studied from FusionPBX and re-implemented independently in NEXVARY's `FusionPbxConfigService`:

1. **Per-domain directory**: Extension configs write to `directory/{sip_domain}/{number}.xml` where `sip_domain` is each tenant's unique SIP context name. This is the standard FreeSWITCH pattern for isolating tenants.

2. **user_context = domain**: The `user_context` FreeSWITCH variable for each extension is set to the tenant's domain name, not `default`. This ensures registered extensions are placed into the correct dialplan context.

3. **Inbound transfer target**: Inbound routes use `transfer {dest} XML {domain}` to route calls into the tenant's specific context, not the global `public` or `default` context.

4. **Per-domain outbound dialplan**: Outbound route XML is written to `dialplan/{domain}/` so it is only reachable from extensions authenticated in that domain.

### NEXVARY files implementing these patterns

| NEXVARY file | Description |
|-------------|-------------|
| `app/Services/PBX/FusionPbxConfigService.php` | Rewrote to use per-domain paths throughout |
| `app/Services/PBX/ExtensionService.php` | Resolves tenant SIP domain before writing configs |
| `app/Models/Tenant.php` | Added `sipDomain()` to resolve per-tenant FreeSWITCH context |
| `app/Http/Controllers/PBX/RouteController.php` | Full implementation using domain-scoped routing |

---

## FreeSWITCH

| Field | Value |
|-------|-------|
| Project | FreeSWITCH |
| Repository | https://github.com/signalwire/freeswitch |
| Version used | 1.10.11 |
| License | Mozilla Public License 1.1 (MPL 1.1) |

NEXVARY connects to FreeSWITCH via its native Event Socket Library (ESL) protocol.
No FreeSWITCH source code is bundled in the NEXVARY application layer.
The Docker image is built separately and published to GHCR.

---

## spandsp (FreeSWITCH fork)

| Field | Value |
|-------|-------|
| Project | spandsp (FreeSWITCH fork) |
| Repository | https://github.com/freeswitch/spandsp |
| License | LGPL 2.1 |
| Usage | Build-time dependency for FreeSWITCH Docker image (not distributed in application) |

---

*Last updated: 2026-11-23*
