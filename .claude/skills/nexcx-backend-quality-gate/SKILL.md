---
name: "nexcx-backend-quality-gate"
description: "NEXVARY CX backend quality gate — prevents recurring Laravel backend test failures. Use before declaring any backend task complete, before opening a PR, after touching models/factories/migrations, or when CI is red. Triggers on: 'run quality gate', 'check backend', 'backend quality', 'is the backend ready', 'verify backend', 'nexcx-backend-quality-gate'."
---

# NEXVARY CX Backend Quality Gate

You are a Laravel 12 / PHP 8.3 backend verification engineer for NEXVARY CX Cloud. Your job is to run a structured checklist that **prevents the class of regression that caused repeated CI failures** on the feature/stage-1-foundation branch.

## Context

- **Framework:** Laravel 12, PHP 8.3+
- **ORM:** Eloquent with Spatie Permission, multi-tenancy via `tenant_id` on most models
- **Test runner:** Pest 3.x with `pestphp/pest-plugin-laravel`
- **Database:** PostgreSQL 16 in CI, SQLite in local unit tests
- **Tenant model:** `App\Models\Tenant` — `HasFactory`, global scope `TenantScope`
- **Known regression class:** Missing `HasFactory` on new models, broken factory relationships, missing foreign keys in migrations, cross-tenant data leaks

## Quality Gate Checklist

Work through every step in order. **Never skip a step.** Report the actual result for each step — not a prediction.

### Step 1 — Model Inspection

For every model touched in this change:

- [ ] `use HasFactory;` is present where a factory exists in `database/factories/`
- [ ] All fillable fields match migration columns exactly (no typos, no missing columns)
- [ ] All `belongsTo` relationships reference existing models with correct foreign keys
- [ ] Models that belong to a tenant have `tenant_id` (nullable or required as appropriate)
- [ ] Global scope (`TenantScope`) is applied where multi-tenant isolation is required
- [ ] No `$guarded = []` without justification

### Step 2 — Factory Inspection

For every factory touched or used by affected tests:

- [ ] Factory exists in `database/factories/`
- [ ] `definition()` provides all required (non-nullable, no-default) fields
- [ ] Foreign key fields use `Tenant::factory()`, `User::factory()` etc. — not hardcoded IDs
- [ ] Sequences or states that override `tenant_id` do so explicitly and correctly

### Step 3 — Migration Inspection

For every migration touched:

- [ ] All foreign key constraints reference existing tables (check migration order)
- [ ] `tenant_id` columns are present where required by the model
- [ ] Column types match what the factory/model expects (e.g. `string` vs `integer`)
- [ ] Down methods correctly reverse the up methods

### Step 4 — Relationship Verification

For every relationship used in affected tests:

- [ ] `hasMany`, `belongsTo`, `hasOne` foreign key column names match migrations
- [ ] Cascades (`onDelete('cascade')`) are present for child records
- [ ] Pivot tables (if any) have both foreign keys

### Step 5 — Tenant Isolation Verification

- [ ] Cross-tenant access is blocked: a user from Tenant A cannot read Tenant B's resources
- [ ] Superadmin bypasses tenant scope where explicitly designed
- [ ] No global queries without scope bypass that would return all-tenant data unintentionally

### Step 6 — Roles and Permissions

- [ ] Required roles (`admin`, `superadmin`, `agent`) are seeded or created in tests via `Role::findOrCreate()`
- [ ] Permission checks (`can`, `hasPermissionTo`) are present on sensitive routes/controllers
- [ ] Tests that test authorization actually assert 403/401 on unauthorized access

### Step 7 — Run Affected Tests

```bash
cd /path/to/nexvary-cx-cloud
php artisan test --filter=<AffectedTestClass> --env=testing
```

Record the **exact output**. If any test fails, fix the root cause before proceeding.

### Step 8 — Run Full Backend Suite

```bash
php artisan test --env=testing
```

Record the **exact pass/fail count and any failure messages**. Do not proceed to Step 9 until all tests pass.

### Step 9 — PostgreSQL 16 Verification

Confirm the test suite passes against PostgreSQL 16 (not just SQLite):

```bash
DB_CONNECTION=pgsql php artisan test --env=testing
```

If running locally without PostgreSQL, note this clearly — it will be verified by CI.

### Step 10 — CI Verification

After pushing:

- [ ] Open the GitHub Actions run URL
- [ ] Confirm all checks pass (green)
- [ ] Record the actual CI run URL and commit SHA
- [ ] Do **not** report predicted results — only confirmed CI results

## Completion Criteria

The backend quality gate passes when:

1. All 10 steps above are completed with no failures
2. All Pest tests pass (actual run, not predicted)
3. CI is green on the pushed commit
4. No TODO or placeholder code remains in touched files
5. This report is attached to the relevant GitHub issue or PR

## Failure Protocol

If any step fails:

1. Fix the root cause (not the symptom)
2. Restart the checklist from Step 1 for affected files
3. Do not declare success until all 10 steps pass
4. Document the root cause in the PR description

## Anti-Patterns to Reject

- "Tests should pass" — only actual run results count
- Commenting out failing tests
- Using `$this->markTestSkipped()` to hide failures
- Faking factory outputs with hardcoded values
- Ignoring PostgreSQL-specific failures
