<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name'            => $name,
            'slug'            => Str::slug($name) . '-' . Str::random(6),
            'plan'            => 'standard',
            'status'          => 'active',
            'timezone'        => 'UTC',
            'locale'          => 'en',
            'max_extensions'  => 50,
            'max_trunks'      => 5,
            'max_dids'        => 10,
            'max_users'       => 20,
        ];
    }
}
