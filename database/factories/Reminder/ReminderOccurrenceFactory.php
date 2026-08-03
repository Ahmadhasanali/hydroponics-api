<?php

namespace Database\Factories\Reminder;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderOccurrence>
 */
class ReminderOccurrenceFactory extends Factory
{
    protected $model = ReminderOccurrence::class;

    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory(),
            'scheduled_at' => now()->addDay()->setTime(8, 0),
            'advance_notify_at' => null,
            'advance_notified_at' => null,
            'notified_at' => null,
            'status' => ReminderStatus::Pending,
            'completed_by_type' => null,
            'completed_by_id' => null,
            'completed_at' => null,
        ];
    }
}
