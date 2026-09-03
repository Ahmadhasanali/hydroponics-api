<?php

namespace Tests\Feature\Telegram;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\FinancialCategory;
use App\Models\MessagingAccount;
use App\Models\MessagingLinkCode;
use App\Models\TelegramPendingSale;
use App\Models\TelegramPendingTransaction;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\SalesService;
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

    public function test_gemini_without_category_prompts_keyboard(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        FinancialCategory::factory()->forFarm($farm->id)->expense()->create(['name' => 'Nutrisi AB Mix']);

        $acc = MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '556']);

        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generate')->once()->andReturn([
            'text' => null,
            'function_calls' => [['name' => 'create_financial_transaction', 'args' => ['type' => 'expense', 'amount' => 300000]]],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('buildCategoryKeyboard')->once()->andReturn(['inline_keyboard' => []]);
        $telegram->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);

        $this->app->instance(GeminiService::class, $gemini);
        $this->app->instance(TelegramService::class, $telegram);

        (new ProcessTelegramUpdate(['message' => ['chat' => ['id' => '556'], 'text' => 'beli pupuk abmix 300 ribu']]))->handle($telegram, $gemini);

        $this->assertDatabaseHas('telegram_pending_transactions', ['messaging_account_id' => $acc->id, 'status' => 'awaiting_category']);
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

    public function test_create_sale_creates_pending_and_summary(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        $acc = MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '601']);

        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generate')->once()->andReturn([
            'text' => null,
            'function_calls' => [[
                'name' => 'create_sale',
                'args' => [
                    'farm_id' => $farm->id,
                    'customer_name' => 'Warung Sari',
                    'items' => [['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 3, 'price' => 21000]],
                ],
            ]],
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('buildSaleConfirmKeyboard')->once()->andReturn(['inline_keyboard' => []]);
        $telegram->shouldReceive('sendMessage')->once()->with('601', Mockery::on(fn ($text) => str_contains($text, 'Warung Sari') && str_contains($text, '63.000')), Mockery::any())->andReturn(['ok' => true]);

        $this->app->instance(GeminiService::class, $gemini);
        $this->app->instance(TelegramService::class, $telegram);

        (new ProcessTelegramUpdate(['message' => ['chat' => ['id' => '601'], 'text' => 'jual 3kg selada 63 ribu ke Warung Sari']]))->handle($telegram, $gemini);

        $this->assertDatabaseHas('telegram_pending_sales', ['messaging_account_id' => $acc->id, 'farm_id' => $farm->id, 'status' => 'awaiting_confirm']);
    }

    public function test_sale_confirm_creates_sale_payment_and_new_customer(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        Account::factory()->cash()->create(['farm_id' => $farm->id]);

        $acc = MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '602']);
        $pending = TelegramPendingSale::factory()->create([
            'messaging_account_id' => $acc->id,
            'chat_id' => '602',
            'farm_id' => $farm->id,
            'customer_id' => null,
            'customer_name' => 'Warung Baru',
            'customer_phone' => '081111',
            'sale_date' => now()->toDateString(),
            'due_date' => null,
            'items' => [['product_id' => null, 'product_name' => 'Selada', 'unit' => 'kg', 'qty' => 2, 'price' => 21000]],
            'status' => 'awaiting_confirm',
            'expires_at' => now()->addMinutes(5),
        ]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('answerCallbackQuery')->once()->with(Mockery::any(), '✅ Penjualan disimpan');
        $telegram->shouldReceive('editMessageText')->once()->andReturn(['ok' => true]);

        $this->app->instance(TelegramService::class, $telegram);
        $this->app->instance(GeminiService::class, Mockery::mock(GeminiService::class));

        $update = [
            'callback_query' => [
                'id' => 'cq_sale',
                'data' => 'sale_confirm:'.$pending->id,
                'from' => ['id' => 602],
                'message' => ['chat' => ['id' => '602'], 'message_id' => 3],
            ],
        ];

        (new ProcessTelegramUpdate($update))->handle($telegram, app(GeminiService::class));

        $this->assertDatabaseHas('customers', ['farm_id' => $farm->id, 'name' => 'Warung Baru']);
        $sale = \App\Models\Farm\Sale::where('farm_id', $farm->id)->firstOrFail();
        $this->assertSame(42000.0, (float) $sale->total_amount);
        $this->assertSame('paid', app(SalesService::class)->status($sale));
        $this->assertDatabaseMissing('telegram_pending_sales', ['id' => $pending->id]);
    }

    public function test_sale_cancel_deletes_pending(): void
    {
        $acc = MessagingAccount::factory()->create();
        $pending = TelegramPendingSale::factory()->create(['messaging_account_id' => $acc->id, 'expires_at' => now()->addMinutes(5)]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('answerCallbackQuery')->once();
        $telegram->shouldReceive('editMessageText')->once()->andReturn(['ok' => true]);

        $update = [
            'callback_query' => [
                'id' => 'cq_sale_cancel',
                'data' => 'sale_cancel:'.$pending->id,
                'from' => ['id' => 123],
                'message' => ['chat' => ['id' => '123'], 'message_id' => 4],
            ],
        ];

        (new ProcessTelegramUpdate($update))->handle($telegram, app(GeminiService::class));

        $this->assertDatabaseMissing('telegram_pending_sales', ['id' => $pending->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
