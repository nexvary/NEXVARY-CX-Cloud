<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberTranslation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'direction', 'pattern', 'replace',
        'strip_prefix', 'add_prefix', 'privacy_enabled',
        'caller_id_override', 'priority', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'privacy_enabled' => 'boolean',
            'enabled'         => 'boolean',
            'priority'        => 'integer',
        ];
    }
}
