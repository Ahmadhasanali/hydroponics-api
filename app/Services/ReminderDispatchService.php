<?php

namespace App\Services;

use App\Enums\ReminderStatus;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;

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
        $this->dispatchAcknowledgementResends();
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
        foreach ($reminder->targets as $target) {
            $recipient = $target->targetable;

            if (! $recipient instanceof User && ! $recipient instanceof Staff) {
                continue;
            }

            // Named route web (farm.reminders.show, staff.reminders.calendar)
            // tidak ada di API-only app ini.
            $recipientUrl = $url ?? $this->recipientUrl($reminder, $recipient);

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

    private function recipientUrl(Reminder $reminder, User|Staff $recipient): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        // Staff diarahkan ke kalender staff di SPA, user diarahkan ke detail
        // reminder di SPA.
        return $recipient instanceof Staff
            ? $frontendUrl.'/staff/reminders/calendar'
            : $frontendUrl.'/farm/'.$reminder->farm_id.'/reminders/'.$reminder->id;
    }

    private function dispatchAcknowledgementResends(): void
    {
        $cutoff = now()->subMinutes((int) config('reminders.resend_after_minutes', 30));
        $table = (new ReminderNotificationDelivery)->getTable();

        ReminderNotificationDelivery::query()
            ->where('kind', 'main')
            ->whereNull('opened_at')
            ->where('sent_at', '<=', $cutoff)
            ->whereNotExists(function ($q) use ($table): void {
                $q->selectRaw('1')
                    ->from("{$table} as prior")
                    ->whereColumn('prior.occurrence_id', "{$table}.occurrence_id")
                    ->whereColumn('prior.notifiable_type', "{$table}.notifiable_type")
                    ->whereColumn('prior.notifiable_id', "{$table}.notifiable_id")
                    ->where('prior.kind', 'resend');
            })
            ->whereHas('occurrence', function ($q): void {
                $q->where('status', ReminderStatus::Pending->value)
                    ->whereNotNull('notified_at');
            })
            ->whereHas('reminder', function ($q): void {
                $q->where('is_active', true);
            })
            ->with(['occurrence.reminder', 'notifiable'])
            ->get()
            ->each(function (ReminderNotificationDelivery $delivery): void {
                $reminder = $delivery->occurrence->reminder;
                $recipient = $delivery->notifiable;

                if (! $recipient instanceof User && ! $recipient instanceof Staff) {
                    return;
                }

                $this->push->sendToUser(
                    $recipient,
                    $reminder->title,
                    $reminder->body,
                    $this->recipientUrl($reminder, $recipient),
                );

                ReminderNotificationDelivery::query()->create([
                    'reminder_id' => $reminder->id,
                    'occurrence_id' => $delivery->occurrence_id,
                    'notifiable_type' => $recipient::class,
                    'notifiable_id' => $recipient->id,
                    'kind' => 'resend',
                    'sent_at' => now(),
                ]);
            });
    }
}
