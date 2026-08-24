<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'type' => 'expense',
            'amount' => fake()->numberBetween(10_000, 500_000),
            'transaction_date' => fake()->dateTimeBetween('-60 days')->format('Y-m-d'),
            'source' => 'manual',
            'status' => 'approved',
            'user_id' => User::factory(),
            'note' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FinancialTransaction $tx): void {
            if ($tx->category_id === null) {
                $tx->category_id = FinancialCategory::factory()->create([
                    'farm_id' => $tx->farm_id,
                    'type' => $tx->type,
                ])->id;
            }
        });
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => 'expense']);
    }

    public function telegram(): static
    {
        return $this->state(fn () => ['source' => 'telegram']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending_approval']);
    }
}
