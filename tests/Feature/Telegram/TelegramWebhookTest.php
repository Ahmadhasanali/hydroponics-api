<?php

namespace Tests\Feature\Telegram;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\MessagingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_webhook_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/telegram/webhook', ['message' => ['chat' => ['id' => 123], 'text' => 'halo']])->assertOk();

        Queue::assertPushed(ProcessTelegramUpdate::class);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        config(['telegram.webhook_secret' => 'secret123']);

        $this->postJson('/api/v1/telegram/webhook', ['message' => ['chat' => ['id' => 123], 'text' => 'halo']], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertStatus(403);

        $this->postJson('/api/v1/telegram/webhook', ['message' => ['chat' => ['id' => 123], 'text' => 'halo']], ['X-Telegram-Bot-Api-Secret-Token' => 'secret123'])->assertOk();
    }

    public function test_link_code_requires_auth(): void
    {
        $this->postJson('/api/v1/telegram/link-code')->assertUnauthorized();
    }

    public function test_link_code_generates_6char(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/api/v1/telegram/link-code')->assertOk();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $res->json('data.code'));
        $this->assertNotNull($res->json('data.expires_at'));
    }

    public function test_status_linked_false_when_no_account(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->getJson('/api/v1/telegram/status')->assertOk();

        $this->assertFalse($res->json('data.linked'));
    }

    public function test_status_linked_true(): void
    {
        $user = User::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id, 'external_id' => '999']);

        $res = $this->actingAs($user)->getJson('/api/v1/telegram/status')->assertOk();

        $this->assertTrue($res->json('data.linked'));
        $this->assertSame('999', $res->json('data.external_id'));
    }

    public function test_unlink_removes_account(): void
    {
        $user = User::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/v1/telegram/unlink')->assertOk();

        $this->assertDatabaseMissing('messaging_accounts', ['user_id' => $user->id]);
    }
}
