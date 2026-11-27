<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHours extends Model
{
    use HasUuids, HasFactory, BelongsToTenant;

    protected $table = 'business_hours';

    protected $fillable = [
        'tenant_id', 'name', 'schedule', 'holidays', 'timezone', 'enabled',
    ];

    protected $casts = [
        'schedule'  => 'array',
        'holidays'  => 'array',
        'enabled'   => 'boolean',
    ];
}
