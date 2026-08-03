<?php

namespace App\Services;

use App\Enums\RecurrenceType;
use App\Models\Reminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ReminderRecurrenceService
{
    public function nextOccurrenceAfter(Reminder $reminder, CarbonInterface $after): ?CarbonInterface
    {
        if (! $reminder->isRecurring()) {
            return null;
        }

        $type = $reminder->recurrenceType();
        $current = Carbon::instance($after);

        return match ($type) {
            RecurrenceType::Interval => $this->nextInterval($reminder, $current),
            RecurrenceType::Weekly => $this->nextWeekly($reminder, $current),
            RecurrenceType::Monthly => $this->nextMonthly($reminder, $current),
            default => null,
        };
    }

    /**
     * @return array<int, CarbonInterface>
     */
    public function generateOccurrences(Reminder $reminder, CarbonInterface $from, CarbonInterface $until, int $max = 100): array
    {
        $occurrences = [];

        if (! $reminder->isRecurring()) {
            $start = Carbon::instance($reminder->starts_at);

            if ($start->between($from, $until)) {
                $occurrences[] = $start;
            }

            return $occurrences;
        }

        $cursor = Carbon::instance($reminder->starts_at);
        $from = Carbon::instance($from);
        $until = Carbon::instance($until);

        // Rentang bersifat granularitas hari: occurrence pada hari $until tetap dihitung.
        $untilEnd = $until->copy()->endOfDay();

        if ($cursor->lt($from)) {
            $cursor = $this->advanceTo($reminder, $cursor, $from);
        }

        while ($cursor && $cursor->lte($untilEnd) && count($occurrences) < $max) {
            if ($cursor->gte($from)) {
                $occurrences[] = $cursor->copy();
            }

            $cursor = $this->nextOccurrenceAfter($reminder, $cursor);
        }

        return $occurrences;
    }

    private function advanceTo(Reminder $reminder, CarbonInterface $cursor, CarbonInterface $from): ?CarbonInterface
    {
        while ($cursor && $cursor->lt($from)) {
            $next = $this->nextOccurrenceAfter($reminder, $cursor);

            if (! $next || $next->lte($cursor)) {
                return null;
            }

            $cursor = $next;
        }

        return $cursor;
    }

    private function nextInterval(Reminder $reminder, CarbonInterface $current): CarbonInterface
    {
        $everyDays = max(1, (int) ($reminder->recurrence['every_days'] ?? 1));

        return $current->copy()->addDays($everyDays);
    }

    private function nextWeekly(Reminder $reminder, CarbonInterface $current): CarbonInterface
    {
        $days = array_map('strtolower', $reminder->recurrence['days_of_week'] ?? []);
        $days = array_values(array_intersect(
            ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            $days,
        ));

        if ($days === []) {
            return $current->copy()->addWeek();
        }

        $weekdays = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0];
        $mapped = array_map(fn (string $d) => $weekdays[$d], $days);
        $mapped = array_values(array_unique($mapped));
        sort($mapped);

        $candidate = $current->copy()->addDay();

        // Iterasi hari dengan batas aman (max 7 iterasi = cukup untuk satu minggu penuh).
        // Mulai dari hari berikutnya agar selalu mengembalikan occurrence yang benar-benar setelah $after.
        for ($i = 0; $i < 7; $i++) {
            if (in_array($candidate->dayOfWeekIso % 7, $mapped, true)) {
                return $candidate;
            }

            $candidate->addDay();
        }

        return $candidate;
    }

    private function nextMonthly(Reminder $reminder, CarbonInterface $current): CarbonInterface
    {
        $days = array_map('intval', $reminder->recurrence['days_of_month'] ?? []);
        $days = array_values(array_filter($days, fn (int $d) => $d >= 1 && $d <= 31));
        sort($days);

        if ($days === []) {
            return $current->copy()->addMonth();
        }

        $day = (int) $current->format('d');
        $month = $current->copy()->startOfMonth();

        foreach ($days as $targetDay) {
            if ($targetDay > $day) {
                $candidate = $month->copy()->setDay($targetDay);

                if ($candidate->format('m') !== $month->format('m')) {
                    continue; // tanggal tidak valid di bulan ini
                }

                return $candidate->setTimeFrom($current);
            }
        }

        $nextMonth = $month->copy()->addMonth();

        foreach ($days as $targetDay) {
            $candidate = $nextMonth->copy()->setDay($targetDay);

            if ($candidate->format('m') === $nextMonth->format('m')) {
                return $candidate->setTimeFrom($current);
            }
        }

        return $current->copy()->addMonth();
    }
}
