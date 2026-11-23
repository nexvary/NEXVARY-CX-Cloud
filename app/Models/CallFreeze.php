<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallFreeze extends Model
{
    use HasUuids;

    protected $fillable = [
        'scope', 'tenant_id', 'trunk_id', 'reason',
        'activated_by', 'deactivated_by',
        'activated_at', 'deactivated_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'activated_at'   => 'datetime',
            'deactivated_at' => 'datetime',
            'active'         => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function trunk(): BelongsTo
    {
        return $this->belongsTo(Trunk::class);
    }

    public function activatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function deactivatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
