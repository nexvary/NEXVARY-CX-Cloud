<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MohClass extends Model
{
    use HasUuids, HasFactory, BelongsToTenant;

    protected $table = 'moh_classes';

    protected $fillable = [
        'tenant_id', 'name', 'source', 'stream_url', 'shuffle', 'enabled',
    ];

    protected $casts = [
        'shuffle' => 'boolean',
        'enabled' => 'boolean',
    ];
}
