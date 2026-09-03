<?php

namespace App\Services;

use App\Enums\ReminderStatus;
use App\Models\Farm\Sale;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;

class SaleReminderService
{
    private int $advanceMinutes;

    public function __construct()
    {
        $this->advanceMinutes = (int) config('sales.reminder_advance_minutes', 1440);
    }

    public function createForSale(Sale $sale, User $creator): void
    {
        $dueDate = $sale->due_date?->copy()->startOfDay();
        if ($dueDate === null || $sale->trashed()) {
            return;
        }

        $this->cancelExistingForSale($sale);

        $reminder = Reminder::create([
            'farm_id' => $sale->farm_id,
            'created_by_type' => User::class,
            'created_by_id' => $creator->id,
            'title' => 'Piutang jatuh tempo — '.($sale->customer->name ?? 'Pelanggan'),
            'body' => 'Sisa tagihan Rp '.number_format($this->remaining($sale), 0, ',', '.').
                ' jatuh tempo pada '.$dueDate->translatedFormat('d M Y').'.',
            'starts_at' => $dueDate,
            'recurrence' => ['type' => 'none'],
            'advance_notify_minutes' => $this->advanceMinutes,
            'is_active' => true,
        ]);

        ReminderTarget::create([
            'reminder_id' => $reminder->id,
            'targetable_type' => Sale::class,
            'targetable_id' => $sale->id,
        ]);

        $farm = $sale->farm;
        $farm?->users()
            ->wherePivotIn('role', ['owner', 'manager'])
            ->get()
            ->each(function (User $user) use ($reminder): void {
                ReminderTarget::create([
                    'reminder_id' => $reminder->id,
                    'targetable_type' => User::class,
                    'targetable_id' => $user->id,
                ]);
            });

        ReminderOccurrence::create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $dueDate,
            'advance_notify_at' => $dueDate->copy()->subMinutes($this->advanceMinutes),
            'status' => ReminderStatus::Pending,
        ]);
    }

    public function markDoneIfPaid(Sale $sale): void
    {
        $reminder = $this->findReminderForSale($sale);
        if ($reminder === null) {
            return;
        }

        $remaining = $this->remaining($sale);
        if ($remaining > 0) {
            return;
        }

        $reminder->occurrences()
            ->where('status', ReminderStatus::Pending->value)
            ->get()
            ->each(function (ReminderOccurrence $occurrence) use ($reminder): void {
                $occurrence->markDone($reminder->created_by_type, $reminder->created_by_id);
            });
    }

    public function syncAfterSaleUpdate(Sale $sale): void
    {
        $reminder = $this->findReminderForSale($sale);

        if ($sale->due_date === null) {
            if ($reminder) {
                $reminder->update(['is_active' => false]);
            }

            return;
        }

        if ($reminder) {
            $reminder->update([
                'title' => 'Piutang jatuh tempo — '.($sale->customer->name ?? 'Pelanggan'),
                'starts_at' => $sale->due_date->copy()->startOfDay(),
                'advance_notify_minutes' => $this->advanceMinutes,
                'is_active' => true,
            ]);

            $reminder->occurrences()
                ->whereNull('notified_at')
                ->whereNull('advance_notified_at')
                ->delete();

            ReminderOccurrence::create([
                'reminder_id' => $reminder->id,
                'scheduled_at' => $sale->due_date->copy()->startOfDay(),
                'advance_notify_at' => $sale->due_date->copy()->startOfDay()->subMinutes($this->advanceMinutes),
                'status' => ReminderStatus::Pending,
            ]);

            return;
        }

        $this->createForSale($sale, User::find($sale->user_id) ?? $sale->farm?->users()->wherePivot('role', 'owner')->first());
    }

    public function deactivateForSale(Sale $sale): void
    {
        $this->findReminderForSale($sale)?->update(['is_active' => false]);
    }

    private function findReminderForSale(Sale $sale): ?Reminder
    {
        return Reminder::query()
            ->whereHas('targets', fn ($q) => $q
                ->where('targetable_type', Sale::class)
                ->where('targetable_id', $sale->id))
            ->first();
    }

    private function cancelExistingForSale(Sale $sale): void
    {
        Reminder::query()
            ->whereHas('targets', fn ($q) => $q
                ->where('targetable_type', Sale::class)
                ->where('targetable_id', $sale->id))
            ->update(['is_active' => false]);
    }

    private function remaining(Sale $sale): float
    {
        $paid = (float) $sale->payments()->sum('amount');

        return round((float) $sale->total_amount - $paid, 2);
    }
}
