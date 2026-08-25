<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Support\Carbon;

class GetFinancialSummaryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_financial_summary';
    }

    public function description(): string
    {
        return 'Ringkasan keuangan farm (pemasukan, pengeluaran, laba bersih, kategori terbesar) untuk periode tertentu.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm. Opsional jika user hanya punya satu farm.'],
                'period' => ['type' => 'STRING', 'description' => 'this_month atau last_month. Default this_month.'],
                'from' => ['type' => 'STRING', 'description' => 'Tanggal mulai YYYY-MM-DD opsional (mengabaikan period).'],
                'to' => ['type' => 'STRING', 'description' => 'Tanggal akhir YYYY-MM-DD opsional.'],
            ],
            'required' => [],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user);

        if ($farms->isEmpty()) {
            return ['error' => 'Anda belum tergabung di farm mana pun.'];
        }

        $farmId = null;
        if (array_key_exists('farm_id', $args)) {
            if (! is_numeric($args['farm_id']) || (int) $args['farm_id'] < 1) {
                return ['error' => 'Parameter farm_id tidak valid.'];
            }

            $farmId = (int) $args['farm_id'];
        }

        if ($farmId !== null) {
            $farms = $farms->filter(fn (Farm $farm): bool => $farm->id === $farmId);

            if ($farms->isEmpty()) {
                return ['error' => 'Farm tidak ditemukan atau Anda tidak memiliki akses.'];
            }
        }

        try {
            [$from, $to] = $this->resolveRange($args);
        } catch (\Throwable) {
            return ['error' => 'Format tanggal tidak valid. Gunakan YYYY-MM-DD.'];
        }
        $service = app(FinanceService::class);

        $payload = $farms
            ->map(fn (Farm $farm): array => $this->payload($service, $farm, $from, $to))
            ->values()
            ->all();

        return ['data' => count($payload) === 1 ? $payload[0] : $payload];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $args): array
    {
        if (! empty($args['from']) || ! empty($args['to'])) {
            try {
                $from = Carbon::parse($args['from'] ?? now()->startOfMonth());
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Format tanggal tidak valid. Gunakan YYYY-MM-DD.');
            }

            try {
                $to = Carbon::parse($args['to'] ?? now());
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Format tanggal tidak valid. Gunakan YYYY-MM-DD.');
            }

            return [$from, $to];
        }

        if (($args['period'] ?? 'this_month') === 'last_month') {
            $base = now()->subMonthNoOverflow();

            return [$base->copy()->startOfMonth(), $base->copy()->endOfMonth()];
        }

        return [now()->copy()->startOfMonth(), now()];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FinanceService $service, Farm $farm, Carbon $from, Carbon $to): array
    {
        $summary = $service->summary($farm, $from, $to, 'day');

        return [
            'farm_id' => $farm->id,
            'farm_name' => $farm->name,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'income' => $summary['income'],
            'expense' => $summary['expense'],
            'net' => $summary['net'],
            'top_categories' => array_slice($summary['categories'], 0, 3),
        ];
    }
}
