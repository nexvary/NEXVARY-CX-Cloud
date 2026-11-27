<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ivr_menu_id', 'digit', 'description', 'destination_type', 'destination_id',
    ];

    public function menu(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IvrMenu::class, 'ivr_menu_id');
    }
}
