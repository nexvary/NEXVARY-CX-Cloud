<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trunk extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'provider',
        'sip_host', 'sip_port', 'transport', 'proxy',
        'auth_mode', 'username', 'password', 'realm',
        'caller_id_name', 'caller_id_number', 'caller_id_in_from',
        'register', 'priority', 'enabled',
        'codecs', 'provider_meta',
        'preset_key', 'preset_version',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'register'          => 'boolean',
            'enabled'           => 'boolean',
            'caller_id_in_from' => 'boolean',
            'sip_port'          => 'integer',
            'priority'          => 'integer',
            'codecs'            => 'array',
            'provider_meta'     => 'array',
        ];
    }

    public function dids(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Did::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
