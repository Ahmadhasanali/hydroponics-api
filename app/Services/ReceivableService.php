<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farm\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ReceivableService
{
    public function __construct(private readonly SalesService $sales) {}

    public function receivables(Farm $farm, array $filters = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $today = Carbon::today()->toDateString();

        // Ambil semua sale farm, lalu filter di PHP: deterministik & aman lintas DB.
        $sales = Sale::query()
            ->where('farm_id', $farm->id)
            ->with(['customer', 'payments'])
            ->orderByRaw('COALESCE(due_date, sale_date) asc')
            ->get()
            ->filter(function (Sale $sale) use ($status, $today): bool {
                $remaining = $this->sales->remaining($sale);
                if ($remaining <= 0) {
                    return false;
                }
                if ($status === 'overdue' && (! $sale->due_date || $sale->due_date->toDateString() >= $today)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'customer' => $sale->customer,
                'sale_date' => $sale->sale_date->toDateString(),
                'due_date' => $sale->due_date?->toDateString(),
                'total_amount' => (float) $sale->total_amount,
                'paid_amount' => $this->sales->paidAmount($sale),
                'remaining_amount' => $this->sales->remaining($sale),
                'status' => $this->sales->status($sale),
                'overdue' => (bool) $sale->due_date && $sale->due_date->toDateString() < $today,
            ]);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = $filters['per_page'] ?? 20;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $sales->forPage($page, $perPage)->values(),
            $sales->count(),
            $perPage,
            $page,
        );
    }

    public function summary(Farm $farm): array
    {
        $sales = Sale::query()
            ->where('farm_id', $farm->id)
            ->with('payments')
            ->get();

        $totalRemaining = 0.0;
        $customers = [];
        $overdueCount = 0;
        $today = Carbon::today()->toDateString();

        foreach ($sales as $sale) {
            $remaining = $this->sales->remaining($sale);
            if ($remaining <= 0) {
                continue;
            }
            $totalRemaining += $remaining;
            $customers[$sale->customer_id] = true;
            if ($sale->due_date && $sale->due_date->toDateString() < $today) {
                $overdueCount++;
            }
        }

        return [
            'total_remaining' => round($totalRemaining, 2),
            'customer_count' => count($customers),
            'overdue_count' => $overdueCount,
        ];
    }
}
