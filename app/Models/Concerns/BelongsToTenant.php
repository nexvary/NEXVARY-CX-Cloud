<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;

/**
 * Trait for models that belong to a specific tenant.
 * Automatically applies TenantScope and sets tenant_id on creation.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (self $model) {
            if (empty($model->tenant_id)) {
                $tenantId = auth()->user()?->tenant_id;
                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
                     ->where($this->qualifyColumn('tenant_id'), $tenantId);
    }
}
