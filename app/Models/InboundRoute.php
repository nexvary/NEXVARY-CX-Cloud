<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundRoute extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'inbound_routes';

    protected $fillable = [
        'tenant_id', 'did', 'name', 'destination_type', 'destination',
        'trunk_id', 'priority', 'enabled', 'time_conditions',
    ];

    protected function casts(): array
    {
        return [
            'enabled'         => 'boolean',
            'priority'        => 'integer',
            'time_conditions' => 'array',
        ];
    }

    public function trunk(): BelongsTo
    {
        return $this->belongsTo(Trunk::class);
    }
}
