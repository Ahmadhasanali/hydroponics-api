<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farm\FinancialTransaction;
use Illuminate\Support\Carbon;

class FinanceService
{
    public function summary(Farm $farm, Carbon $from, Carbon $to, string $groupBy = 'day'): array
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';

        $transactions = FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->where('status', 'approved')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->with('category')
            ->get();

        $income = (float) $transactions->where('type', 'income')->sum('amount');
        $expense = (float) $transactions->where('type', 'expense')->sum('amount');

        $series = $transactions
            ->groupBy(fn (FinancialTransaction $t): string => $this->periodKey($t->transaction_date, $groupBy))
            ->map(fn ($group): array => [
                'period' => $this->periodKey($group->first()->transaction_date, $groupBy),
                'income' => round((float) $group->where('type', 'income')->sum('amount'), 2),
                'expense' => round((float) $group->where('type', 'expense')->sum('amount'), 2),
            ])
            ->sortBy('period')
            ->values()
            ->all();

        $categories = $transactions
            ->groupBy(fn (FinancialTransaction $t): string => $t->category->name.'|'.$t->type)
            ->map(fn ($group): array => [
                'category' => $group->first()->category->name,
                'type' => $group->first()->type,
                'total' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            'series' => $series,
            'categories' => $categories,
        ];
    }

    private function periodKey(Carbon|string $date, string $groupBy): string
    {
        $date = Carbon::parse($date);

        return match ($groupBy) {
            'month' => $date->copy()->startOfMonth()->toDateString(),
            'week' => $date->copy()->startOfWeek()->toDateString(),
            default => $date->toDateString(),
        };
    }
}
