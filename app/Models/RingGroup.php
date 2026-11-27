<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RingGroup extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'extension', 'strategy', 'timeout',
        'destination', 'destination_type', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'timeout' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(RingGroupMember::class);
    }
}
