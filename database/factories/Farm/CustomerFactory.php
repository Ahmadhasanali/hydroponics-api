<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->company().' '.fake()->randomElement(['Warung', 'Toko']),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
