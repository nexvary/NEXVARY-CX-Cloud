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
        'tenant_id', 'number', 'description', 'sip_password', 'voicemail_pin',
        'call_timeout', 'forward_enabled', 'forward_destination',
        'dnd_enabled', 'voicemail_enabled', 'call_waiting', 'enabled',
        'mac_address', 'device_type', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'dnd_enabled'      => 'boolean',
            'forward_enabled'  => 'boolean',
            'voicemail_enabled'=> 'boolean',
            'call_waiting'     => 'boolean',
            'enabled'          => 'boolean',
            'call_timeout'     => 'integer',
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
