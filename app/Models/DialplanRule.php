<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DialplanRule extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'context', 'priority',
        'continue', 'conditions', 'actions', 'enabled',
    ];

    protected $casts = [
        'priority'   => 'integer',
        'continue'   => 'boolean',
        'conditions' => 'array',
        'actions'    => 'array',
        'enabled'    => 'boolean',
    ];
}
