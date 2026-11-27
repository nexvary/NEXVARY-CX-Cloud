<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallBlacklist extends Model
{
    use HasUuids, HasFactory, BelongsToTenant;

    protected $table = 'call_blacklists';

    protected $fillable = [
        'tenant_id', 'pattern', 'pattern_type', 'direction', 'description', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
