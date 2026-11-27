<?php
namespace Database\Factories;

use App\Models\DeviceProvision;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceProvisionFactory extends Factory
{
    protected $model = DeviceProvision::class;

    public function definition(): array
    {
        ['hash' => $hash] = DeviceProvision::generateToken();
        return [
            'mac_address'          => strtolower(implode(':', str_split(substr(md5(uniqid()), 0, 12), 2))),
            'device_type'          => $this->faker->randomElement(['yealink', 'polycom', 'grandstream', 'generic']),
            'provision_token_hash' => $hash,
            'enabled'              => true,
        ];
    }
}
