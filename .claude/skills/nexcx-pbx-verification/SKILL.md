---
name: "nexcx-pbx-verification"
description: "NEXVARY CX PBX verification — FreeSWITCH integration, SIP registration, extension provisioning, dialplan, Ring Groups, IVR, inbound/outbound routing. Use before declaring any PBX feature complete or when PBX integration tests fail. Triggers on: 'pbx verification', 'freeswitch check', 'sip test', 'nexcx-pbx-verification', 'verify pbx', 'check extensions'."
---

# NEXVARY CX PBX Verification

You are a FreeSWITCH/VoIP integration engineer for NEXVARY CX Cloud. Apply this checklist when verifying PBX features.

## Architecture Context

- **Switch:** FreeSWITCH (self-hosted, per-instance or shared cluster)
- **Control interface:** ESL (Event Socket Library) on port 8021
- **Configuration:** XML dialplans generated per-tenant/domain
- **Protocol:** SIP over UDP/TCP; TLS+SRTP for secure extensions
- **Tenant isolation:** Each tenant has a distinct SIP domain (e.g., `tenant1.nexvary.internal`)
- **Laravel integration:** `App\Services\PBX\FreeSwitchService` manages ESL commands
- **Extension range:** Configurable per tenant (default 1000–9999)

## Verification Checklist

### 1 — FreeSWITCH Connectivity

- [ ] ESL connection succeeds: `FreeSwitchService::ping()` returns true
- [ ] FreeSWITCH version is logged on startup and matches expected version
- [ ] ESL password is loaded from config (not hardcoded)
- [ ] ESL connection failures are logged at ERROR level and return a typed exception (not generic Exception)
- [ ] The service handles ESL socket timeout gracefully (no uncaught exceptions)

### 2 — SIP Registration

- [ ] A newly provisioned extension can register to FreeSWITCH using the generated credentials
- [ ] SIP registration uses the correct tenant domain
- [ ] Re-registration after credential rotation works within the grace period
- [ ] An unprovisioned extension ID cannot register (directory lookup returns not-found)
- [ ] Registration events from FreeSWITCH are received and stored (`Extension.sip_registered_at` updated)

### 3 — Extension Provisioning

- [ ] Creating an Extension via the UI/API triggers `FreeSwitchService::provisionExtension()`
- [ ] The provisioned XML/JSON directory entry includes: extension number, password hash, tenant domain, codec list
- [ ] Deprovisioning an extension removes the directory entry and forcibly unregisters any active SIP session
- [ ] Extension number uniqueness is enforced **per tenant** (not globally)
- [ ] Extension passwords meet minimum entropy requirements

### 4 — Dialplan Generation

- [ ] Each tenant's dialplan is isolated: a call to extension 1001 within Tenant A does not reach Tenant B
- [ ] Generated dialplan XML is valid (can be parsed by FreeSWITCH's XML parser)
- [ ] Outbound routes use the correct gateway per tenant
- [ ] Inbound DID routing resolves to the correct extension or ring group
- [ ] Voicemail routing (unanswered/busy) is included in the dialplan

### 5 — Ring Groups

- [ ] Ring group members are provisioned correctly in FreeSWITCH
- [ ] Strategy (simultaneous, round-robin, hunt) is reflected in the dialplan
- [ ] Adding/removing a member re-provisions the ring group immediately
- [ ] A ring group call that goes unanswered falls through to voicemail or the configured failover destination
- [ ] Ring groups cannot contain members from a different tenant

### 6 — IVR Menus

- [ ] IVR menu prompts are uploaded, stored, and referenced by the correct path in the dialplan
- [ ] DTMF digit routing maps to the correct extension, ring group, or submenu
- [ ] Invalid digit input plays an error prompt and loops (up to configured max attempts)
- [ ] IVR timeout (no input) routes to the configured destination
- [ ] IVR menus are tenant-scoped and not visible to other tenants

### 7 — Inbound Routing

- [ ] An inbound call to a tenant DID reaches the correct destination (extension/ring group/IVR)
- [ ] DIDs not associated with any tenant return a "not in service" announcement
- [ ] Time conditions (business hours) route calls correctly based on server time zone
- [ ] DID routing changes take effect without restarting FreeSWITCH (XML reload or ESL reconfig command)

### 8 — Outbound Routing

- [ ] Extensions can place outbound calls via the configured SIP trunk
- [ ] Caller ID is set to the tenant's configured outbound CLI (not the internal extension number)
- [ ] International dialling restrictions are enforced per tenant plan
- [ ] No chargeable outbound calls are placed during automated testing — use ESL `originate` to loopback or test extensions only

### 9 — Local Call-Flow Tests (No Carrier Traffic)

Run all PBX integration tests using loopback or test extensions only:

```php
it('routes inbound DID to ring group', function () {
    // Uses FreeSwitchService mock or loopback — no carrier call
    $mock = FreeSwitchService::fake();
    $did  = Did::factory()->create(['extension_id' => null, 'ring_group_id' => $ringGroup->id]);

    $result = $mock->simulateInbound($did->number);

    expect($result->destination)->toBe("ring_group_{$ringGroup->id}");
});
```

- [ ] All PBX tests use mocked/faked ESL — no live calls to Telnyx or PSTN
- [ ] Tests do not require a running FreeSWITCH instance (unit tests only)
- [ ] Integration tests are tagged `@group pbx` and skipped in CI unless `FREESWITCH_ESL_HOST` is set

### 10 — Security

- [ ] SIP credentials are not exposed in dialplan XML served over HTTP
- [ ] FreeSWITCH management interface (port 8021 ESL) is bound to localhost only
- [ ] FreeSWITCH HTTP API (port 8080) is disabled or firewall-restricted
- [ ] SIP over TLS is the only allowed transport for external extensions
- [ ] SRTP is enforced for media on TLS-registered extensions

## Completion Criteria

PBX verification passes when:

1. All checked items above are confirmed (or documented as out-of-scope for the current change)
2. All PBX-related Pest tests pass
3. No live carrier calls were placed during verification
4. Security items in Step 10 are confirmed

## Known Limitations

- FreeSWITCH is not installed on the developer's Windows machine — PBX integration tests require the Docker/VM environment or a CI environment with FreeSWITCH
- Record any item requiring a live environment as "PENDING — requires CI/staging" rather than marking it passed
