<?php

namespace Tests\Feature\Telegram;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\MessagingAccount;
use App\Models\MessagingLinkCode;
use App\Models\TelegramPendingTransaction;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessTelegramUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_link_code_via_telegram_creates_account(): void
    {
        $user = User::factory()->create();
        MessagingLinkCode::factory()->create(['user_id' => $user->id, 'code' => 'ABC123', 'expires_at' => now()->addSeconds(60)]);

        $svc = Mockery::mock(TelegramService::class);
        $svc->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);

        $this->app->instance(TelegramService::class, $svc);
        $this->app->instance(GeminiService::class, Mockery::mock(GeminiService::class));

        (new ProcessTelegramUpdate(['message' => ['chat' => ['id' => '999'], 'text' => 'ABC123']]))->handle(app(TelegramService::class), app(GeminiService::class));

        $this->assertDatabaseHas('messaging_accounts', ['user_id' => $user->id, 'external_id' => '999']);
        $this->assertDatabaseHas('messaging_link_codes', ['code' => 'ABC123', 'used_at' => now()]);
    }

    public function test_not_linked_prompts_connect(): void
    {
        $svc = Mockery::mock(TelegramService::class);
        $svc->shouldReceive('sendMessage')->once()->with('321', 'Hubungkan dulu di HydroFarm → Pengaturan → Telegram.')->andReturn(['ok' => true]);

        $this->app->instance(TelegramService::class, $svc);
        $this->app->instance(GeminiService::class, Mockery::mock(GeminiService::class));

        (new ProcessTelegramUpdate(['message' => ['chat' => ['id' => '321'], 'text' => 'beli pupuk 100 ribu']]))->handle(app(TelegramService::class), app(GeminiService::class));

        $this->assertTrue(true);
    }

    public function test_gemini_generates_pending_and_sends_confirm(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $cat = FinancialCategory::factory()->forFarm($farm->id)->expense()->create();

        $acc = MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '555']);

        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generate')->once()->andReturn([
            'text' => null,
            'function_calls' => [['name' => 'create_financial_transaction', 'args' => ['type' => 'expense', 'category_id' => $cat->id, 'amount' => 300000, 'transaction_date' => now()->toDateString(), 'note' => 'pupuk']]],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('buildConfirmKeyboard')->once()->andReturn(['inline_keyboard' => []]);
        $telegram->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);

        $this->app->instance(GeminiService::class, $gemini);
        $this->app->instance(TelegramService::class, $telegram);

        (new ProcessTelegramUpdate(['message' => ['chat' => ['id' => '555'], 'text' => 'beli pupuk abmix 300 ribu']]))->handle($telegram, $gemini);

        $this->assertDatabaseHas('telegram_pending_transactions', ['messaging_account_id' => $acc->id, 'status' => 'awaiting_confirm']);
    }

    public function test_confirm_creates_transaction(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $cat = FinancialCategory::factory()->forFarm($farm->id)->expense()->create();

        $acc = MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '777']);
        $pending = TelegramPendingTransaction::factory()->create([
            'messaging_account_id' => $acc->id,
            'chat_id' => '777',
            'farm_id' => $farm->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => 50000,
            'transaction_date' => now()->toDateString(),
            'status' => 'awaiting_confirm',
            'expires_at' => now()->addMinutes(5),
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('answerCallbackQuery')->once()->with(Mockery::any(), '✅ Disimpan');
        $telegram->shouldReceive('editMessageText')->once()->andReturn(['ok' => true]);

        $this->app->instance(TelegramService::class, $telegram);
        $this->app->instance(GeminiService::class, Mockery::mock(GeminiService::class));

        $update = [
            'callback_query' => [
                'id' => 'cq1',
                'data' => 'confirm:'.$pending->id,
                'from' => ['id' => 777],
                'message' => ['chat' => ['id' => '777'], 'message_id' => 1],
            ],
        ];

        (new ProcessTelegramUpdate($update))->handle($telegram, app(GeminiService::class));

        $this->assertDatabaseHas('financial_transactions', ['farm_id' => $farm->id, 'category_id' => $cat->id, 'amount' => 50000, 'source' => 'telegram']);
        $this->assertDatabaseMissing('telegram_pending_transactions', ['id' => $pending->id]);
    }

    public function test_cancel_deletes_pending(): void
    {
        $acc = MessagingAccount::factory()->create();
        $pending = TelegramPendingTransaction::factory()->create(['messaging_account_id' => $acc->id, 'expires_at' => now()->addMinutes(5)]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('answerCallbackQuery')->once();
        $telegram->shouldReceive('editMessageText')->once()->andReturn(['ok' => true]);

        $update = [
            'callback_query' => [
                'id' => 'cq2',
                'data' => 'cancel:'.$pending->id,
                'from' => ['id' => 123],
                'message' => ['chat' => ['id' => '123'], 'message_id' => 2],
            ],
        ];

        (new ProcessTelegramUpdate($update))->handle($telegram, app(GeminiService::class));

        $this->assertDatabaseMissing('telegram_pending_transactions', ['id' => $pending->id]);
    }

    public function test_farm_required_creates_pending_awaiting_farm(): void
    {
        $owner = User::factory()->create();
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $farm1->users()->attach($owner->id, ['role' => 'owner']);
        $farm2->users()->attach($owner->id, ['role' => 'manager']);
        $cat = FinancialCategory::factory()->forFarm($farm1->id)->expense()->create();

        $acc = MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '888']);

        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generate')->once()->andReturn([
            'text' => null,
            'function_calls' => [['name' => 'create_financial_transaction', 'args' => ['type' => 'expense', 'category_id' => $cat->id, 'amount' => 100000]]],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('buildFarmKeyboard')->once()->andReturn(['inline_keyboard' => []]);
        $telegram->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);

        $this->app->instance(GeminiService::class, $gemini);

        (new ProcessTelegramUpdate(['message' => ['chat' => ['id' => '888'], 'text' => 'beli pupuk 100 ribu']]))->handle($telegram, $gemini);

        $this->assertDatabaseHas('telegram_pending_transactions', ['messaging_account_id' => $acc->id, 'status' => 'awaiting_farm']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
