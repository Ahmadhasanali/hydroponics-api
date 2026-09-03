<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Customer;
use App\Models\Farm\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'customer_id' => Customer::factory(),
            'sale_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'due_date' => null,
            'total_amount' => 63000,
            'note' => null,
            'user_id' => User::factory(),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => ['due_date' => now()->addDays(7)->toDateString()]);
    }
}
