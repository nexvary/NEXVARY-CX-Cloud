<?php

namespace Database\Factories;

use App\Models\IvrMenu;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class IvrMenuFactory extends Factory
{
    protected $model = IvrMenu::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => Tenant::factory(),
            'name'         => $this->faker->words(3, true),
            'timeout'      => 10,
            'max_failures' => 3,
            'enabled'      => true,
        ];
    }
}
