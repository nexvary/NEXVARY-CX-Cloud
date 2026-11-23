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
 *  - Authenticated with tenant_id: scopes to that tenant
 *  - Authenticated without tenant_id (superadmin): no automatic scope applied;
 *    controllers are required to add explicit scoping for superadmin paths
 *
 * A missing tenant_id NEVER silently grants global access to tenant data.
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
        }
        // Superadmin (tenant_id = null): no auto-scope — controllers must
        // apply ->where('tenant_id', $id) or withoutGlobalScope(TenantScope::class)
    }
}
