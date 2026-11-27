<?php

namespace Database\Factories;

use App\Models\CallAgent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallAgentFactory extends Factory
{
    protected $model = CallAgent::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'name'        => $this->faker->name(),
            'status'      => 'available',
            'skill_level' => 1,
            'enabled'     => true,
        ];
    }
}
