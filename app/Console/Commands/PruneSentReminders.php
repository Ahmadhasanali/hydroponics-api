<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use Illuminate\Console\Command;

class PruneSentReminders extends Command
{
    protected $signature = 'reminders:prune-sent';

    protected $description = 'Menghapus pengingat sekali-kirim yang sudah terkirim melebihi masa retensi riwayat';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('reminders.history_retention_days', 90));

        $staleIds = ReminderOccurrence::query()
            ->select('reminder_id')
            ->groupBy('reminder_id')
            ->havingRaw('MAX(COALESCE(notified_at, completed_at, scheduled_at)) <= ?', [$cutoff])
            ->pluck('reminder_id');

        if ($staleIds->isEmpty()) {
            $this->info('Tidak ada pengingat yang diprune.');

            return self::SUCCESS;
        }

        $prunable = Reminder::query()
            ->withTrashed()
            ->whereIn('id', $staleIds)
            ->where(function ($q): void {
                $q->where('recurrence->type', 'none')
                    ->orWhereNull('recurrence');
            })
            ->pluck('id');

        $prunable->chunk(100)->each(function ($chunk): void {
            Reminder::query()
                ->withTrashed()
                ->whereIn('id', $chunk)
                ->get()
                ->each->forceDelete();
        });

        $this->info("{$prunable->count()} pengingat diprune.");

        return self::SUCCESS;
    }
}
