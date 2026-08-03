<?php

namespace App\Services;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;

class ReminderDispatchService
{
    public function __construct(
        private readonly ReminderRecurrenceService $recurrence,
        private readonly PushNotificationService $push,
    ) {}

    public function dispatchDue(): void
    {
        $this->dispatchAdvanceNotifications();
        $this->dispatchMainNotifications();
    }

    private function dispatchAdvanceNotifications(): void
    {
        ReminderOccurrence::query()
            ->where('status', ReminderStatus::Pending->value)
            ->whereNotNull('advance_notify_at')
            ->where('advance_notify_at', '<=', now())
            ->whereNull('advance_notified_at')
            ->with(['reminder.targets.targetable'])
            ->get()
            ->each(function (ReminderOccurrence $occurrence) {
                $reminder = $occurrence->reminder;

                $this->sendToTargets(
                    $reminder,
                    "{$reminder->title} — sebentar lagi",
                    "Pengingat awal: {$reminder->body}",
                );

                $occurrence->update(['advance_notified_at' => now()]);
            });
    }

    private function dispatchMainNotifications(): void
    {
        ReminderOccurrence::query()
            ->where('status', ReminderStatus::Pending->value)
            ->where('scheduled_at', '<=', now())
            ->whereNull('notified_at')
            ->with(['reminder.targets.targetable'])
            ->get()
            ->each(function (ReminderOccurrence $occurrence) {
                $reminder = $occurrence->reminder;

                $this->sendToTargets(
                    $reminder,
                    $reminder->title,
                    $reminder->body,
                    route('farm.reminders.show', [$reminder->farm_id, $reminder->id]),
                );

                $occurrence->update(['notified_at' => now()]);

                if ($reminder->isRecurring()) {
                    $this->createNextOccurrence($reminder, $occurrence);
                }
            });
    }

    private function createNextOccurrence(Reminder $reminder, ReminderOccurrence $occurrence): void
    {
        $next = $this->recurrence->nextOccurrenceAfter($reminder, $occurrence->scheduled_at);

        if (! $next) {
            return;
        }

        $alreadyExists = ReminderOccurrence::query()
            ->where('reminder_id', $reminder->id)
            ->where('scheduled_at', $next)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        ReminderOccurrence::query()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $next,
            'advance_notify_at' => $reminder->advance_notify_minutes
                ? $next->copy()->subMinutes($reminder->advance_notify_minutes)
                : null,
            'status' => ReminderStatus::Pending,
        ]);
    }

    private function sendToTargets(Reminder $reminder, string $title, string $body, ?string $url = null): void
    {
        foreach ($reminder->targets as $target) {
            $recipient = $target->targetable;

            if ($recipient) {
                $this->push->sendToUser($recipient, $title, $body, $url);
            }
        }
    }
}
