<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundRoute extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'outbound_routes';

    protected $fillable = [
        'tenant_id', 'name', 'dial_pattern', 'trunk_id', 'trunk_prefix',
        'priority', 'enabled', 'allowed_roles',
    ];

    protected function casts(): array
    {
        return [
            'enabled'       => 'boolean',
            'priority'      => 'integer',
            'allowed_roles' => 'array',
        ];
    }

    public function trunk(): BelongsTo
    {
        return $this->belongsTo(Trunk::class);
    }
}
