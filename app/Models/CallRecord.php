<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallRecord extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'uuid', 'caller_id_number', 'caller_id_name', 'destination_number', 'direction',
        'duration_seconds', 'billable_seconds', 'hangup_cause',
        'recording_path', 'extension_id', 'trunk_id', 'billing_account_id',
        'started_at', 'answered_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'billable_seconds' => 'integer',
            'started_at'       => 'datetime',
            'answered_at'      => 'datetime',
            'ended_at'         => 'datetime',
        ];
    }

    public function extension(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function trunk(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Trunk::class);
    }

    public function agent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
