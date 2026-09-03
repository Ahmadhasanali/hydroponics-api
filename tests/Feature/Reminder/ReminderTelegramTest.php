<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Farm\Sale;
use App\Models\MessagingAccount;
use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Services\ReminderDispatchService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReminderTelegramTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_receivable_reminder_sends_telegram_to_linked_user(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $sale = Sale::factory()->create(['farm_id' => $farm->id, 'due_date' => now()->toDateString()]);

        MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '901']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'title' => 'Piutang jatuh tempo — Warung Uji',
            'body' => 'Tagih Warung Uji Rp 50.000 sebelum hari ini.',
            'starts_at' => now(),
            'recurrence' => null,
            'is_active' => true,
        ]);
        ReminderTarget::factory()->create(['reminder_id' => $reminder->id, 'targetable_type' => $owner::class, 'targetable_id' => $owner->id]);
        ReminderTarget::factory()->create(['reminder_id' => $reminder->id, 'targetable_type' => $sale::class, 'targetable_id' => $sale->id]);

        // Occurrence jatuh tempo sekarang → dispatch main mengirim.
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')->once()->with('901', Mockery::on(fn ($text) => str_contains($text, 'Piutang jatuh tempo — Warung Uji')))->andReturn(['ok' => true]);
        $this->app->instance(TelegramService::class, $telegram);

        app(ReminderDispatchService::class)->dispatchDue();

        $this->assertDatabaseHas('reminder_occurrences', ['id' => $reminder->occurrences()->first()->id, 'notified_at' => now()]);
    }

    public function test_non_receivable_reminder_does_not_send_telegram(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        MessagingAccount::factory()->create(['user_id' => $owner->id, 'external_id' => '902']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'title' => 'Cek pH tank',
            'body' => 'Lakukan monitoring.',
            'starts_at' => now(),
            'recurrence' => null,
            'is_active' => true,
        ]);
        ReminderTarget::factory()->create(['reminder_id' => $reminder->id, 'targetable_type' => $owner::class, 'targetable_id' => $owner->id]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldNotReceive('sendMessage');
        $this->app->instance(TelegramService::class, $telegram);

        app(ReminderDispatchService::class)->dispatchDue();

        // Telegram tidak boleh dipanggil untuk reminder non-piutang.
        $this->assertSame(1, ReminderNotificationDelivery::query()->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
