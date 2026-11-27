<?php
namespace Database\Factories;

use App\Models\DialplanRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class DialplanRuleFactory extends Factory
{
    protected $model = DialplanRule::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->words(3, true),
            'context'    => 'default',
            'priority'   => $this->faker->numberBetween(10, 200),
            'continue'   => false,
            'conditions' => [['field' => 'destination_number', 'expression' => '^\d{4}$']],
            'actions'    => [['application' => 'bridge', 'data' => 'user/${destination_number}']],
            'enabled'    => true,
        ];
    }
}
