<?php

namespace Database\Factories;

use App\Models\SipProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SipProfileFactory extends Factory
{
    protected $model = SipProfile::class;

    public function definition(): array
    {
        return [
            'tenant_id'               => null,
            'name'                    => $this->faker->unique()->word() . ' Profile',
            'type'                    => $this->faker->randomElement(['internal', 'external']),
            'enabled'                 => false,
            'sip_port'                => null,
            'sip_ip'                  => null,
            'rtp_ip'                  => null,
            'ext_sip_ip'              => null,
            'ext_rtp_ip'              => null,
            'nat_enabled'             => false,
            'rtp_timeout'             => 300,
            'rtp_hold_timeout'        => 1800,
            'codecs'                  => ['PCMU', 'PCMA'],
            'tls_enabled'             => false,
            'tls_port'                => null,
            'srtp_mode'               => 'disabled',
            'apply_inbound_acl'       => null,
            'aggressive_nat_detection'=> false,
            'settings'                => null,
        ];
    }
}
