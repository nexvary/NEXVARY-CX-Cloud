<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RateTable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'currency', 'description', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function rates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Rate::class);
    }
}
