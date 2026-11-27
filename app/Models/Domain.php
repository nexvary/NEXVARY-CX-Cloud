<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'verified', 'verification_token',
        'certificate_path', 'certificate_expires_at', 'tls_enabled', 'enabled',
    ];

    protected $casts = [
        'verified'                 => 'boolean',
        'tls_enabled'              => 'boolean',
        'enabled'                  => 'boolean',
        'certificate_expires_at'   => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isCertificateExpiringSoon(int $days = 30): bool
    {
        if (!$this->certificate_expires_at) {
            return false;
        }

        return $this->certificate_expires_at->diffInDays(now()) <= $days;
    }
}
