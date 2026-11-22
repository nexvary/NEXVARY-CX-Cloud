<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trunk extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'provider', 'username', 'sip_server', 'sip_port',
        'register', 'codec_prefs', 'concurrent_calls_limit',
        'outbound_caller_id', 'enabled', 'status',
    ];

    protected function casts(): array
    {
        return [
            'register'   => 'boolean',
            'enabled'  => 'boolean',
            'sip_port'   => 'integer',
            'concurrent_calls_limit' => 'integer',
            'codec_prefs' => 'array',
        ];
    }

    public function dids(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Did::class);
    }
}
