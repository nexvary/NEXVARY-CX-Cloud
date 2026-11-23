# NEXVARY CX Cloud — Claude Engineering Guide

## Role

You are acting as a Senior Laravel/PHP engineer, FreeSWITCH integration specialist, security auditor, and DevOps engineer for **NEXVARY CX Cloud** — a self-hosted, multi-tenant Unified Communications platform.

## Architecture

### Backend (Laravel 12 / PHP 8.3+)

| Component | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.3+ |
| ORM | Eloquent with global scopes for tenant isolation |
| Auth | Laravel Sanctum (SPA + API tokens) |
| Permissions | Spatie Laravel Permission (`admin`, `superadmin`, `agent` roles) |
| Multi-tenancy | `tenant_id` on most models + `TenantScope` global scope |
| Queue | Redis (Predis) |
| PDF | barryvdh/laravel-dompdf |
| Encryption | defuse/php-encryption (SIP credentials, secrets) |
| 2FA | pragmarx/google2fa |
| Activity log | spatie/laravel-activitylog |

### Frontend (React / Inertia.js)

| Component | Technology |
|---|---|
| Framework | React 18 + TypeScript |
| SPA bridge | Inertia.js v2 |
| Styling | Tailwind CSS v4 |
| Forms | react-hook-form + Zod |
| Charts | Recharts |
| Softphone | sip.js v0.21 |
| i18n | i18next + react-i18next |
| Icons | lucide-react |
| Build | Vite |

### Infrastructure

| Component | Technology |
|---|---|
| Database | PostgreSQL 16 |
| Cache/Queue | Redis |
| PBX | FreeSWITCH (ESL on port 8021) |
| VoIP provider | Telnyx (SIP trunks + SMS) |
| OS targets | Debian 12, Debian 13, Ubuntu 24.04 LTS (CentOS/RHEL not supported — owner decision) |
| CI | GitHub Actions |

### Key Models

- `Tenant` — UUID PK, `HasFactory`, `HasUuids`, `SoftDeletes`; `TenantFactory` exists in `database/factories/`
- `User` — `tenant_id` (nullable for superadmin), `HasFactory`
- `Extension` — unique per tenant (not globally), `tenant_id`, SIP credentials stored encrypted
- `Did` — belongs to `tenant_id`, can be assigned to `Extension` or `RingGroup`
- `Domain` — SIP domain per tenant
- `SmsConversation`, `SmsMessage` — Telnyx SMS, tenant-scoped

## Repository and Branch Policy

| Rule | Detail |
|---|---|
| Development branch | `feature/stage-1-foundation` |
| Main branch | `master` |
| PR target | `feature/stage-1-foundation` → `master` via PR #1 |
| Merge PR #1 | **NEVER** without explicit owner approval |
| Deploy to production | **NEVER** without explicit owner approval |
| Paid carrier calls/SMS | **NEVER** trigger during development or testing |

## Mandatory Tests Before Declaring Success

**Never** declare a backend task complete without all of the following:

1. `php artisan test --env=testing` passes (actual run, not predicted)
2. All affected Pest tests pass individually
3. CI is green on the pushed commit (actual GitHub Actions URL)
4. No `TODO`, `FIXME`, or `dd()` / `dump()` calls remain in production code

For frontend tasks:
1. `npm run typecheck` passes
2. `npm run lint` passes
3. Playwright visual QA screenshots captured (actual screenshots, not mockups)
4. No browser console errors

## Backend Quality Gates

Before every PR or significant backend change, run `/nexcx-backend-quality-gate`:

1. `HasFactory` present on models with factories
2. Factory `definition()` provides all required fields
3. Foreign keys exist in migrations
4. `tenant_id` present and scoped correctly
5. Roles/permissions seeded in tests via `Role::findOrCreate()`
6. All Pest tests pass
7. Full suite passes on PostgreSQL 16

## Security Requirements

Before every PR that touches auth/authorization/webhooks/PBX, run `/nexcx-security-review`:

- **Fail-closed**: Telnyx webhook signature verification **always** rejects invalid/missing signatures — never processes payload on verification failure
- **Tenant isolation**: Every Eloquent query that returns tenant data applies scope
- **SIP credentials**: Never in logs, API responses, or Inertia page props
- **API tokens**: Scoped, never logged, revocable
- **DID ownership**: Only assignable within the same tenant
- Do not post Critical or High security findings on the public GitHub issue — use a private advisory

## Arabic/English UI Requirements

- Arabic (`ar`) and English (`en`) must both work correctly
- Arabic pages must have `dir="rtl"` on the root element
- Directional icons must be mirrored for RTL
- Run `/nexcx-visual-qa` before declaring any frontend task complete

## GitHub Reporting Requirements

For every significant task, post a report on [Issue #2](https://github.com/nexvary/NEXVARY-CX-Cloud/issues/2) using this format:

```
[CLAUDE RESULT] CX-###
HEAD / branch:
Commits:
What changed:
Tests and CI run links:
Screenshot artifact links:
Known limitations:
Blockers / next work:
```

Include:
- Actual commit SHA (not predicted)
- Actual CI run URL (not predicted)
- Actual test pass/fail counts

## Owner Restrictions (Non-Negotiable)

- Do **not** merge PR #1 without explicit owner approval
- Do **not** deploy to production
- Do **not** trigger paid SMS or calls
- Do **not** expose credentials or secrets
- Do **not** weaken tests, comment out failures, or use `markTestSkipped()` to hide failures
- Do **not** fake screenshots or measurements

## Available Nexvary Skills

These project-specific skills are in `.claude/skills/`:

| Skill | Purpose | When to Use |
|---|---|---|
| `/nexcx-backend-quality-gate` | Prevents recurring backend test failures | Before every PR; after touching models/factories/migrations |
| `/nexcx-security-review` | Multi-tenant, webhook, SIP security audit | Before any auth/authorization/PBX change |
| `/nexcx-pbx-verification` | FreeSWITCH integration verification | Before declaring any PBX feature complete |
| `/nexcx-visual-qa` | Playwright screenshots, RTL, mobile | Before declaring any frontend feature complete |

## Available Engineering Skills (Global)

These are installed globally via `engineering-skills@claude-code-skills`:

| Skill | Purpose |
|---|---|
| `engineering-skills:code-reviewer` | PHP/Laravel code review |
| `engineering-skills:senior-security` | Security audit |
| `engineering-skills:ai-security` | AI/ML security patterns |
| `engineering-skills:adversarial-reviewer` | Independent adversarial review |
| `engineering-skills:senior-backend` | Laravel/PHP best practices |
| `engineering-skills:senior-devops` | CI/CD and infrastructure |
| `engineering-skills:senior-qa` | Test strategy |
| `engineering-skills:tdd-guide` | Test-driven development |
| `engineering-skills:senior-architect` | Architecture review |

## Multi-Agent Code Review

For significant architectural changes, invoke five independent reviewers using available skills:

1. **Architect**: `engineering-skills:senior-architect`
2. **Laravel/PHP Engineer**: `engineering-skills:senior-backend`
3. **Security Auditor**: `engineering-skills:senior-security` + `/nexcx-security-review`
4. **PBX Engineer**: `/nexcx-pbx-verification`
5. **QA/DevOps**: `engineering-skills:senior-qa` + `engineering-skills:senior-devops`

Do **not** invoke all five agents for trivial changes (typo fixes, cosmetic CSS). Reserve for architecture changes, security-sensitive changes, or major feature additions.

## Common Patterns

### Creating a new model with factory

```php
// app/Models/MyModel.php
class MyModel extends Model
{
    use HasFactory;  // Required if database/factories/MyModelFactory.php exists
    
    protected $fillable = ['tenant_id', 'name'];
}

// database/factories/MyModelFactory.php
class MyModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),  // Always use factory, not hardcoded ID
            'name'      => $this->faker->word(),
        ];
    }
}
```

### Tenant-scoped query

```php
// Always scope to authenticated user's tenant
Extension::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

// Or use the global scope (applied automatically when TenantScope is registered)
Extension::findOrFail($id);  // Will throw 404 if belongs to different tenant
```

### Pest test with tenant isolation

```php
uses(Tests\TestCase::class, RefreshDatabase::class);

it('prevents cross-tenant access', function () {
    [$tenantA, $userA] = makeTenantWithAdmin();
    [$tenantB, $userB] = makeTenantWithAdmin();
    $resource = SomeModel::factory()->create(['tenant_id' => $tenantB->id]);
    
    $this->actingAs($userA)
         ->getJson("/api/resource/{$resource->id}")
         ->assertForbidden();
});
```
