<?php

namespace Database\Factories;

use App\Models\MessagingAccount;
use App\Models\TelegramPendingTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramPendingTransaction>
 */
class TelegramPendingTransactionFactory extends Factory
{
    protected $model = TelegramPendingTransaction::class;

    public function definition(): array
    {
        return [
            'messaging_account_id' => MessagingAccount::factory(),
            'chat_id' => '123',
            'type' => 'expense',
            'amount' => 300000,
            'transaction_date' => now()->toDateString(),
            'status' => 'awaiting_confirm',
            'expires_at' => now()->addMinutes(5),
        ];
    }
}
