<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extension extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'name', 'sip_password', 'voicemail_pin',
        'dnd_enabled', 'call_forward_enabled', 'call_forward_number',
        'timeout', 'enabled', 'context',
    ];

    protected function casts(): array
    {
        return [
            'dnd_enabled'          => 'boolean',
            'call_forward_enabled' => 'boolean',
            'enabled'            => 'boolean',
            'timeout'              => 'integer',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    public function callRecords(): HasMany
    {
        return $this->hasMany(CallRecord::class);
    }
}
