<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => 'Cash',
            'type' => 'cash',
            'balance_initial' => 0,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => ['name' => 'Cash', 'type' => 'cash', 'is_default' => true]);
    }

    public function ewallet(): static
    {
        return $this->state(fn () => ['name' => 'Dana', 'type' => 'ewallet']);
    }

    public function bank(): static
    {
        return $this->state(fn () => ['name' => 'BSI', 'type' => 'bank']);
    }
}
