<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\AccountTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountTransfer> */
class AccountTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'from_account_id' => Account::factory(),
            'to_account_id' => Account::factory(),
            'amount' => 20000,
            'transfer_date' => now()->toDateString(),
        ];
    }
}
