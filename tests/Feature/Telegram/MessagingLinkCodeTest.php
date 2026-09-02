<?php

namespace Tests\Feature\Telegram;

use App\Models\MessagingAccount;
use App\Models\MessagingLinkCode;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MessagingLinkCodeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_link_code_expires_in_60_seconds(): void
    {
        $code = MessagingLinkCode::factory()->create(['expires_at' => now()->addSeconds(60)]);
        $this->assertTrue($code->expires_at->gt(now()));
        $this->assertTrue($code->expires_at->lte(now()->addSeconds(61)));
    }

    public function test_external_id_unique_per_channel(): void
    {
        MessagingAccount::factory()->create(['channel' => 'telegram', 'external_id' => '123']);
        $this->expectException(QueryException::class);
        MessagingAccount::factory()->create(['channel' => 'telegram', 'external_id' => '123']);
    }

    public function test_user_unique_enforces_one_telegram_per_user(): void
    {
        $user = User::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id]);
        $this->expectException(QueryException::class);
        MessagingAccount::factory()->create(['user_id' => $user->id]);
    }
}
