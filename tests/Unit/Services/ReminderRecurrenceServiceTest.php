<?php

namespace Tests\Unit\Services;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderRecurrenceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderRecurrenceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeReminder(array $recurrence): Reminder
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();

        return Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
            'starts_at' => Carbon::parse('2026-08-03 08:00:00'),
            'recurrence' => $recurrence,
        ]);
    }

    public function test_none_recurrence_returns_null_next(): void
    {
        $reminder = $this->makeReminder(['type' => 'none']);
        $service = new ReminderRecurrenceService;

        $this->assertNull($service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00')));
    }

    public function test_interval_recurrence_adds_days(): void
    {
        $reminder = $this->makeReminder(['type' => 'interval', 'every_days' => 3]);
        $service = new ReminderRecurrenceService;

        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertSame('2026-08-06 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_weekly_recurrence_skips_to_next_matching_day(): void
    {
        $reminder = $this->makeReminder(['type' => 'weekly', 'days_of_week' => ['wed', 'fri']]);
        $service = new ReminderRecurrenceService;

        // Senin 03 Agu 2026 08:00 → Rabu 05 Agu 2026 08:00
        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertSame('2026-08-05 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_monthly_recurrence_skips_to_next_matching_day(): void
    {
        $reminder = $this->makeReminder(['type' => 'monthly', 'days_of_month' => [15, 20]]);
        $service = new ReminderRecurrenceService;

        // 03 Agu → 15 Agu
        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertSame('2026-08-15 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_monthly_recurrence_with_day_31_skips_short_month(): void
    {
        $reminder = $this->makeReminder(['type' => 'monthly', 'days_of_month' => [31]]);
        $service = new ReminderRecurrenceService;

        // 31 Jan 2026 08:00 → Februari tidak punya tanggal 31, jadi lompat ke 31 Mar 2026 08:00
        // (bukan meluap ke 03 Mar seperti addMonth() yang lama).
        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-01-31 08:00:00'));

        $this->assertSame('2026-03-31 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_generate_occurrences_in_range(): void
    {
        $reminder = $this->makeReminder(['type' => 'weekly', 'days_of_week' => ['mon']]);
        $service = new ReminderRecurrenceService;

        $occurrences = $service->generateOccurrences(
            $reminder,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $dates = array_map(fn (Carbon $c) => $c->format('Y-m-d'), $occurrences);

        $this->assertContains('2026-08-03', $dates);
        $this->assertContains('2026-08-10', $dates);
        $this->assertContains('2026-08-17', $dates);
        $this->assertContains('2026-08-24', $dates);
        $this->assertContains('2026-08-31', $dates);
    }

    public function test_generate_respects_max_limit(): void
    {
        $reminder = $this->makeReminder(['type' => 'interval', 'every_days' => 1]);
        $service = new ReminderRecurrenceService;

        $occurrences = $service->generateOccurrences(
            $reminder,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2027-08-01'),
            max: 10,
        );

        $this->assertCount(10, $occurrences);
    }
}
