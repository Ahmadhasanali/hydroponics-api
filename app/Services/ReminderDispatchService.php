<?php

namespace App\Services;

use App\Enums\ReminderStatus;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
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
            ->whereHas('reminder', fn ($q) => $q->where('is_active', true))
            ->with(['reminder.targets.targetable'])
            ->get()
            ->each(function (ReminderOccurrence $occurrence) {
                $reminder = $occurrence->reminder;

                $this->sendToTargets(
                    $reminder,
                    $occurrence,
                    "{$reminder->title} — sebentar lagi",
                    "Pengingat awal: {$reminder->body}",
                    null,
                    'advance',
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
            ->whereHas('reminder', fn ($q) => $q->where('is_active', true))
            ->with(['reminder.targets.targetable'])
            ->get()
            ->each(function (ReminderOccurrence $occurrence) {
                $reminder = $occurrence->reminder;

                $this->sendToTargets($reminder, $occurrence, $reminder->title, $reminder->body);

                $occurrence->update(['notified_at' => now()]);

                if ($reminder->isRecurring()) {
                    $this->createNextOccurrence($reminder, $occurrence);
                }
            });
    }

    private function createNextOccurrence(Reminder $reminder, ReminderOccurrence $occurrence): void
    {
        if (! $reminder->is_active) {
            return;
        }

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

    private function sendToTargets(Reminder $reminder, ReminderOccurrence $occurrence, string $title, string $body, ?string $url = null, string $kind = 'main'): void
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        foreach ($reminder->targets as $target) {
            $recipient = $target->targetable;

            if (! $recipient) {
                continue;
            }

            // Staff diarahkan ke kalender staff di SPA, user diarahkan ke
            // detail reminder di SPA. Named route web (farm.reminders.show,
            // staff.reminders.calendar) tidak ada di API-only app ini.
            $recipientUrl = $recipient instanceof Staff
                ? $frontendUrl.'/staff/reminders/calendar'
                : $url ?? $frontendUrl.'/farm/'.$reminder->farm_id.'/reminders/'.$reminder->id;

            $this->push->sendToUser($recipient, $title, $body, $recipientUrl);

            ReminderNotificationDelivery::query()->create([
                'reminder_id' => $reminder->id,
                'occurrence_id' => $occurrence->id,
                'notifiable_type' => $recipient::class,
                'notifiable_id' => $recipient->id,
                'kind' => $kind,
                'sent_at' => now(),
            ]);
        }
    }
}
