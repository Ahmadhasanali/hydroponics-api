<?php

namespace Database\Factories\Farm;

use App\Models\Farm\Account;
use App\Models\Farm\Payment;
use App\Models\Farm\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'account_id' => Account::factory(),
            'amount' => 63000,
            'payment_date' => now()->toDateString(),
            'note' => null,
            'user_id' => User::factory(),
        ];
    }
}
