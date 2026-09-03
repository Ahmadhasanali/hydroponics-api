<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\AccountBalanceAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountBalanceAdjustment> */
class AccountBalanceAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'account_id' => Account::factory(),
            'amount' => fake()->numberBetween(-100000, 500000),
            'adjustment_date' => now()->toDateString(),
            'reason' => fake()->sentence(3),
        ];
    }
}
