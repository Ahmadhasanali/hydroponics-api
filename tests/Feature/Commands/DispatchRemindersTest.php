<?php

namespace Tests\Feature\Commands;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class DispatchRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function makeDueReminder(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $target = User::factory()->create();
        $farm->users()->attach($target->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->subMinute(),
            'recurrence' => ['type' => 'interval', 'every_days' => 1],
        ]);

        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $target->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subMinute(),
        ]);

        return compact('reminder', 'target', 'occurrence');
    }

    public function test_dispatch_sends_push_and_generates_next_occurrence(): void
    {
        ['reminder' => $reminder, 'target' => $target, 'occurrence' => $occurrence] = $this->makeDueReminder();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once()->with(
            Mockery::on(fn (User $user) => $user->is($target)),
            $reminder->title,
            $reminder->body,
            config('app.frontend_url').'/farm/'.$reminder->farm_id.'/reminders/'.$reminder->id,
        );
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertNotNull($occurrence->fresh()->notified_at);
        $this->assertDatabaseHas('reminder_occurrences', [
            'reminder_id' => $reminder->id,
            'status' => 'pending',
        ]);
        $this->assertSame(2, $reminder->occurrences()->count());
    }

    public function test_dispatch_sends_advance_notification(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $target = User::factory()->create();
        $farm->users()->attach($target->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'advance_notify_minutes' => 30,
        ]);

        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $target->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addMinutes(29),
            'advance_notify_at' => now()->subMinute(),
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertNotNull($occurrence->fresh()->advance_notified_at);
        $this->assertNull($occurrence->fresh()->notified_at);
    }

    public function test_dispatch_does_not_resend_notified_occurrence(): void
    {
        ['occurrence' => $occurrence] = $this->makeDueReminder();
        $occurrence->update(['notified_at' => now()]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToUser');
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertNotNull($occurrence->fresh()->notified_at);
    }

    public function test_main_dispatch_records_main_delivery(): void
    {
        ['reminder' => $reminder, 'target' => $target] = $this->makeDueReminder();
        $occurrence = ReminderOccurrence::query()->where('reminder_id', $reminder->id)->firstOrFail();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertDatabaseHas('reminder_notification_deliveries', [
            'reminder_id' => $reminder->id,
            'occurrence_id' => $occurrence->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $target->id,
            'kind' => 'main',
        ]);
    }

    public function test_advance_dispatch_records_advance_delivery(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $target = User::factory()->create();
        $farm->users()->attach($target->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'advance_notify_minutes' => 30,
        ]);

        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $target->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addMinutes(29),
            'advance_notify_at' => now()->subMinute(),
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertDatabaseHas('reminder_notification_deliveries', [
            'occurrence_id' => $occurrence->id,
            'kind' => 'advance',
        ]);
    }
}
