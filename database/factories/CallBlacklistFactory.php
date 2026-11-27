<?php

namespace Database\Factories;

use App\Models\CallBlacklist;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallBlacklistFactory extends Factory
{
    protected $model = CallBlacklist::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => Tenant::factory(),
            'pattern'      => '+1555' . $this->faker->numerify('#######'),
            'pattern_type' => 'exact',
            'direction'    => 'inbound',
            'description'  => null,
            'enabled'      => true,
        ];
    }
}
