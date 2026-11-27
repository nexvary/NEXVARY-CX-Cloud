<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RingGroupMember extends Model
{
    use HasUuids;

    protected $fillable = ['ring_group_id', 'number', 'timeout'];

    public function ringGroup(): BelongsTo
    {
        return $this->belongsTo(RingGroup::class);
    }
}
