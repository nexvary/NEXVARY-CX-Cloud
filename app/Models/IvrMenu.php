<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrMenu extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'description', 'greeting_tts', 'greeting_file',
        'timeout', 'max_failures', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'timeout'      => 'integer',
            'max_failures' => 'integer',
        ];
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IvrEntry::class);
    }
}
