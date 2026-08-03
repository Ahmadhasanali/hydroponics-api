<?php

namespace Database\Factories\Reminder;

use App\Models\Reminder;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderTarget>
 */
class ReminderTargetFactory extends Factory
{
    protected $model = ReminderTarget::class;

    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory(),
            'targetable_type' => User::class,
            'targetable_id' => User::factory(),
        ];
    }
}
