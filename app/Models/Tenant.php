<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'plan', 'status', 'timezone', 'locale', 'settings',
        'max_extensions', 'max_trunks', 'max_dids', 'max_users', 'billing_email',
        'primary_domain_id',
    ];

    protected $casts = [
        'settings'       => 'array',
        'max_extensions' => 'integer',
        'max_trunks'     => 'integer',
        'max_dids'       => 'integer',
        'max_users'      => 'integer',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extension::class);
    }

    public function trunks(): HasMany
    {
        return $this->hasMany(Trunk::class);
    }

    public function dids(): HasMany
    {
        return $this->hasMany(Did::class);
    }

    public function smsConversations(): HasMany
    {
        return $this->hasMany(SmsConversation::class);
    }

    public function billingAccounts(): HasMany
    {
        return $this->hasMany(BillingAccount::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
