<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'type', 'balance', 'credit_limit',
        'currency', 'rate_table_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance'      => 'decimal:6',
            'credit_limit' => 'decimal:6',
            'is_active'    => 'boolean',
        ];
    }

    public function rateTable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RateTable::class);
    }

    public function charges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallCharge::class);
    }
}
