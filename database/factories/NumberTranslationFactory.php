<?php

namespace Database\Factories;

use App\Models\NumberTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class NumberTranslationFactory extends Factory
{
    protected $model = NumberTranslation::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => null,
            'name'               => $this->faker->unique()->words(3, true),
            'direction'          => $this->faker->randomElement(['inbound', 'outbound', 'both']),
            'pattern'            => '\d+',
            'replace'            => '$0',
            'strip_prefix'       => null,
            'add_prefix'         => null,
            'privacy_enabled'    => false,
            'caller_id_override' => null,
            'priority'           => 100,
            'enabled'            => true,
        ];
    }
}
