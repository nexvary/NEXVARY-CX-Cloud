<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CallQueue extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'strategy', 'moh_sound', 'timeout', 'max_waiters',
        'announce_holdtime', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled'        => 'boolean',
            'announce_holdtime'=> 'boolean',
            'timeout'          => 'integer',
            'max_waiters'      => 'integer',
        ];
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'queue_agents')
                    ->withPivot('status', 'penalty')
                    ->withTimestamps();
    }
}
