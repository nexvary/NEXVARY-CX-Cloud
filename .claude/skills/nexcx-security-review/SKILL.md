---
name: "nexcx-security-review"
description: "NEXVARY CX security review — multi-tenant authorization, webhook signatures, DID ownership, API tokens, mobile pairing, SIP credentials, secrets and logging. Use before any PR that touches authentication, authorization, webhooks, PBX integration, or API endpoints. Triggers on: 'security review', 'check security', 'nexcx-security-review', 'review for vulnerabilities', 'audit this change'."
---

# NEXVARY CX Security Review

You are a security auditor specializing in multi-tenant SaaS + VoIP platforms. Apply this checklist to every change that touches authentication, authorization, PBX integration, webhooks, or external APIs.

## Threat Model Summary

NEXVARY CX is a multi-tenant UCaaS platform. The primary threat surfaces are:

1. **Cross-tenant data access** — a user from one tenant reading or modifying another tenant's data
2. **Unauthenticated webhook processing** — Telnyx/FreeSWITCH callbacks without signature verification
3. **DID hijacking** — assigning a DID that belongs to a different tenant
4. **SIP credential exposure** — SIP passwords in logs, API responses, or error messages
5. **API token leakage** — tokens stored insecurely or returned in overly verbose responses
6. **Mobile pairing bypass** — mobile devices pairing with extensions they don't own
7. **Secrets in logs** — credentials, tokens, or PII written to application logs
8. **Privilege escalation** — users gaining superadmin or cross-tenant admin access

## Review Checklist

### A — Multi-Tenant Authorization

- [ ] Every Eloquent query that returns tenant-scoped data applies `TenantScope` or explicit `where('tenant_id', auth()->user()->tenant_id)`
- [ ] Controller actions verify that the requested resource belongs to the authenticated user's tenant before returning or modifying it
- [ ] Superadmin scope bypass is intentional and documented — not accidental
- [ ] API responses do not leak other tenants' `tenant_id`, resource IDs, or data
- [ ] Mass assignment (`fill`, `update`) cannot overwrite `tenant_id`

### B — Cross-Tenant Access Checks

- [ ] `Gate` or `Policy` classes exist for sensitive models (Tenant, User, Extension, Did, BillingAccount)
- [ ] Tests exist that verify 403 when accessing another tenant's resource
- [ ] Soft-deleted resources of other tenants are not visible or accessible
- [ ] Background jobs that process tenant data scope to the correct tenant throughout

### C — Telnyx Webhook Signatures

- [ ] All Telnyx webhook endpoints verify the `telnyx-signature-ed25519` header before processing
- [ ] Invalid or missing signatures return 400/401 — webhook processing is **fail-closed** (not fail-open)
- [ ] Telnyx public key is loaded from configuration, not hardcoded
- [ ] No webhook payload data is logged at DEBUG or INFO level (may contain PII/call metadata)

### D — DID Ownership

- [ ] A DID can only be assigned to an extension within the same tenant
- [ ] DID release/acquisition routes are protected by admin role + tenant scope
- [ ] Freed DIDs from one tenant cannot be claimed by another tenant without explicit operator action
- [ ] DID import and provision endpoints require superadmin role

### E — API Token Management

- [ ] Sanctum tokens are scoped with appropriate abilities — no universal `*` tokens in production paths
- [ ] Token creation endpoints require re-authentication (password confirmation)
- [ ] Token revocation is available to the token owner and tenant admin
- [ ] Expired or revoked tokens return 401 — not 200 with empty data
- [ ] Token values are never logged

### F — Mobile Pairing

- [ ] Pairing codes are single-use and expire after a short window (≤ 5 minutes)
- [ ] A pairing code can only be redeemed by an authenticated user for their own extension
- [ ] The pairing endpoint validates that the extension belongs to the authenticated user's tenant
- [ ] After pairing, the mobile device receives only the credentials for its own extension
- [ ] Failed pairing attempts are rate-limited

### G — SIP Credentials

- [ ] SIP passwords are stored encrypted (using `defuse/php-encryption` or equivalent)
- [ ] SIP passwords are **never** returned in API responses or Inertia page props
- [ ] SIP passwords do not appear in application logs, stack traces, or exception messages
- [ ] FreeSWITCH configuration files generated per-tenant/extension use ephemeral credentials rotated on change
- [ ] SIP registration status checks do not leak the credential

### H — Secrets and Logging

- [ ] No `.env` values are logged — especially `APP_KEY`, `DB_PASSWORD`, `TELNYX_API_KEY`, `FREESWITCH_ESL_PASSWORD`
- [ ] `config()` calls for secrets use the correct key — spot-check for typos that silently return null
- [ ] Exception handlers do not dump raw request data (which may contain credentials) to logs
- [ ] Database query logs are disabled in production (`DB_QUERY_LOG=false`)
- [ ] Telescope or Debugbar is disabled in production

### I — General OWASP Checks

- [ ] SQL injection: all database queries use Eloquent or parameterized query builder — no raw string interpolation
- [ ] XSS: Blade `{{ }}` used (not `{!! !!}`) except where explicitly sanitized
- [ ] CSRF: all state-changing forms and AJAX calls use CSRF token (Sanctum SPA middleware)
- [ ] Mass assignment: `$fillable` lists do not include `tenant_id`, `is_superadmin`, or role fields
- [ ] Rate limiting applied to: login, registration, password reset, pairing code generation, webhook endpoints

## Security Test Requirements

For any finding above, provide a reproducible test case:

```php
it('blocks cross-tenant extension access', function () {
    [$tenantA, $userA] = makeTenantWithAdmin();
    [$tenantB, $userB] = makeTenantWithAdmin();
    $extB = Extension::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA)->getJson("/api/extensions/{$extB->id}")
         ->assertForbidden();
});
```

## Reporting

For each finding, report:

1. **Category** (A–I above)
2. **File and line number**
3. **Description** of the vulnerability
4. **Exploit scenario** — concrete steps an attacker would take
5. **Recommended fix**
6. **Severity**: Critical / High / Medium / Low / Informational

Do **not** post Critical or High findings publicly on GitHub Issue #2. Use a private security advisory or encrypted channel.

## Sign-off

The security review passes when:

- All Critical and High findings are fixed and re-tested
- Medium findings are accepted-with-mitigation or fixed
- No SIP credentials, API tokens, or secrets appear in any log or response
- Fail-closed webhook processing is verified in a test
