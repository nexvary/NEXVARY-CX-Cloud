<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipProfile extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'enabled',
        'sip_port', 'sip_ip', 'rtp_ip', 'ext_sip_ip', 'ext_rtp_ip',
        'nat_enabled', 'rtp_timeout', 'rtp_hold_timeout',
        'codecs', 'tls_enabled', 'tls_port', 'srtp_mode',
        'apply_inbound_acl', 'aggressive_nat_detection', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'enabled'                   => 'boolean',
            'nat_enabled'               => 'boolean',
            'tls_enabled'               => 'boolean',
            'aggressive_nat_detection'  => 'boolean',
            'sip_port'                  => 'integer',
            'tls_port'                  => 'integer',
            'rtp_timeout'               => 'integer',
            'rtp_hold_timeout'          => 'integer',
            'codecs'                    => 'array',
            'settings'                  => 'array',
        ];
    }

    /** Default codec list for a new profile. */
    public static function defaultCodecs(): array
    {
        return ['PCMU', 'PCMA', 'G722', 'G729', 'opus'];
    }
}
