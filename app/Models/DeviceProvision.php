<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceProvision extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'extension_id', 'mac_address', 'device_type',
        'provision_token_hash', 'enabled', 'settings', 'last_provisioned_at',
    ];

    protected $casts = [
        'enabled'              => 'boolean',
        'settings'             => 'array',
        'last_provisioned_at'  => 'datetime',
    ];

    protected $hidden = ['provision_token_hash'];

    public static array $deviceTypes = ['yealink', 'polycom', 'grandstream', 'cisco', 'snom', 'generic'];

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    /** Generate a secure token; store its hash; return the raw token for one-time delivery. */
    public static function generateToken(): array
    {
        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        return ['raw' => $raw, 'hash' => $hash];
    }

    public static function findByToken(string $rawToken): ?static
    {
        return static::withoutGlobalScopes()
            ->where('provision_token_hash', hash('sha256', $rawToken))
            ->where('enabled', true)
            ->first();
    }
}
