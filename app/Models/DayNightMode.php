<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DayNightMode extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'day_night_modes';

    protected $fillable = [
        'tenant_id', 'name', 'mode', 'day_destination', 'night_destination', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
