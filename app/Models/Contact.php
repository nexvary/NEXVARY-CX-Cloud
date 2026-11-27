<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'company', 'notes', 'tags', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'tags'      => 'array',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function smsConversations(): HasMany
    {
        return $this->hasMany(SmsConversation::class);
    }
}
