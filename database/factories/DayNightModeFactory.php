<?php

namespace Database\Factories;

use App\Models\DayNightMode;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DayNightModeFactory extends Factory
{
    protected $model = DayNightMode::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => Tenant::factory(),
            'name'            => $this->faker->words(2, true),
            'mode'            => 'day',
            'day_destination' => '1001',
            'night_destination' => '1002',
            'enabled'         => true,
        ];
    }
}
