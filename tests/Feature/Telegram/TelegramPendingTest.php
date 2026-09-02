<?php

namespace Tests\Feature\Telegram;

use App\Models\MessagingAccount;
use App\Models\TelegramPendingTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TelegramPendingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pending_expires_in_5_minutes(): void
    {
        $acc = MessagingAccount::factory()->create();
        $p = TelegramPendingTransaction::factory()->create(['messaging_account_id' => $acc->id, 'expires_at' => now()->addMinutes(5)]);

        $this->assertTrue($p->expires_at->gt(now()->addMinutes(4)));
    }
}
