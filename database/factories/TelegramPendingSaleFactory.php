<?php

namespace Database\Factories;

use App\Models\MessagingAccount;
use App\Models\TelegramPendingSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramPendingSale>
 */
class TelegramPendingSaleFactory extends Factory
{
    protected $model = TelegramPendingSale::class;

    public function definition(): array
    {
        return [
            'messaging_account_id' => MessagingAccount::factory(),
            'chat_id' => '123',
            'sale_date' => now()->toDateString(),
            'items' => [['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 1, 'price' => 21000]],
            'status' => 'awaiting_confirm',
            'expires_at' => now()->addMinutes(5),
        ];
    }
}
