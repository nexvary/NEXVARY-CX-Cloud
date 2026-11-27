<?php

namespace Database\Factories;

use App\Models\MohClass;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MohClassFactory extends Factory
{
    protected $model = MohClass::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name'      => 'Default',
            'source'    => 'files',
            'stream_url' => null,
            'shuffle'   => false,
            'enabled'   => true,
        ];
    }
}
