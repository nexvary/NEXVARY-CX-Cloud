<?php
namespace Database\Factories;

use App\Models\Extension;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExtensionFactory extends Factory
{
    protected $model = Extension::class;

    public function definition(): array
    {
        static $counter = 1000;
        $counter++;
        return [
            'number'      => (string) $counter,
            'description' => $this->faker->name(),
            'sip_password'=> bcrypt(Str::random(16)),
            'enabled'     => true,
        ];
    }
}
