<?php

namespace Database\Factories;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Employee;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => fake()->randomElement(['indefinido', 'temporal']),
            'base_salary' => fake()->numberBetween(1800, 4500) . '.00',
            'start_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'end_date' => null
        ];
    }
}
