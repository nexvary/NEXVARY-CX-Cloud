<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CallAgent extends Model
{
    use HasUuids, HasFactory, BelongsToTenant;

    protected $table = 'call_agents';

    protected $fillable = [
        'tenant_id', 'extension_id', 'name', 'status', 'skill_level', 'enabled',
    ];

    protected $casts = [
        'enabled'     => 'boolean',
        'skill_level' => 'integer',
    ];

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function queues(): BelongsToMany
    {
        return $this->belongsToMany(CallQueue::class, 'queue_agents', 'call_agent_id', 'call_queue_id')
                    ->withPivot('status', 'penalty')
                    ->withTimestamps();
    }
}
