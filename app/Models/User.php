<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, HasRoles, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'locale', 'timezone',
        'mfa_enabled', 'mfa_secret', 'mfa_recovery_codes', 'mfa_enrolled_at',
        'extension_id', 'active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'last_login_at'      => 'datetime',
            'mfa_enrolled_at'    => 'datetime',
            'password'           => 'hashed',
            'mfa_enabled'        => 'boolean',
            'active'             => 'boolean',
            'mfa_recovery_codes' => 'array',
        ];
    }

    public function extension(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function callRecords(): HasMany
    {
        return $this->hasMany(CallRecord::class, 'agent_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }
}
