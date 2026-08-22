<?php

namespace Database\Factories\Reminder;

use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderNotificationDelivery>
 */
class ReminderNotificationDeliveryFactory extends Factory
{
    protected $model = ReminderNotificationDelivery::class;

    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory(),
            'occurrence_id' => ReminderOccurrence::factory(),
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'kind' => 'main',
            'sent_at' => now(),
            'opened_at' => null,
        ];
    }
}
