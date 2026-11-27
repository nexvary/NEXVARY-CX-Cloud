<?php

namespace Database\Factories;

use App\Models\RingGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RingGroupFactory extends Factory
{
    protected $model = RingGroup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name'      => $this->faker->words(2, true),
            'extension' => (string) $this->faker->numberBetween(5000, 5999),
            'strategy'  => 'simultaneous',
            'timeout'   => 30,
            'enabled'   => true,
        ];
    }
}
