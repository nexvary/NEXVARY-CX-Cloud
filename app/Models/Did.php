<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Did extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'number', 'description', 'trunk_id', 'destination_type',
        'destination_id', 'enabled',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function trunk(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Trunk::class);
    }
}
