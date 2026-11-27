<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that restricts queries to the authenticated user's tenant.
 *
 * Rules:
 *  - Unauthenticated: returns nothing (1=0)
 *  - Authenticated, tenant_id set: scopes to that tenant
 *  - Authenticated, tenant_id null, superadmin role: no auto-scope;
 *    controllers MUST add ->where('tenant_id', $id) or
 *    ->withoutGlobalScope(TenantScope::class) explicitly
 *  - Authenticated, tenant_id null, NOT superadmin: returns nothing (1=0)
 *
 * A missing tenant_id NEVER silently grants global access to tenant data.
 * Superadmin status is verified by role, not inferred from a null tenant_id.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (!$user) {
            $builder->whereRaw('1 = 0');
            return;
        }

        if ($user->tenant_id !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $user->tenant_id);
            return;
        }

        // User has null tenant_id — only a verified superadmin proceeds unscoped.
        // Any other user (e.g. misconfigured account) gets denied.
        if (!$user->hasRole('superadmin')) {
            $builder->whereRaw('1 = 0');
        }

        // Verified superadmin: no auto-scope applied.
        // Controllers must scope explicitly (->where('tenant_id', $id) or
        // ->withoutGlobalScope(TenantScope::class)) for any superadmin path.
    }
}
