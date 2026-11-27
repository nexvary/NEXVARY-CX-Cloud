<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rate_table_id', 'prefix', 'destination_name',
        'rate_per_minute', 'billing_increment', 'minimum_duration', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_minute'  => 'decimal:6',
            'billing_increment'=> 'integer',
            'minimum_duration' => 'integer',
            'enabled'        => 'boolean',
        ];
    }

    public function rateTable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RateTable::class);
    }
}
