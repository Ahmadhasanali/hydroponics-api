<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\User;
use App\Services\ReceivableService;

class GetReceivablesTool extends BaseTool
{
    public function name(): string
    {
        return 'get_receivables';
    }

    public function description(): string
    {
        return 'Menampilkan piutang penjualan ke warung/toko yang belum lunas (total tagihan, jumlah warung, daftar yang menunggak/jatuh tempo). Panggil tool ini saat pengguna bertanya "piutang", "tagihan", "warung yang belum bayar", "yang jatuh tempo", "menagih", atau rekap penjualan kredit yang belum dibayar. Jangan panggil untuk ringkasan pemasukan/pengeluaran finance (gunakan get_financial_summary).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm. Opsional jika user hanya punya satu farm.'],
                'overdue_only' => ['type' => 'BOOLEAN', 'description' => 'true bila hanya ingin piutang yang sudah jatuh tempo / menunggak. Default false (semua piutang belum lunas).'],
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

        if (isset($args['farm_id']) && $args['farm_id'] !== null) {
            if (! is_numeric($args['farm_id']) || (int) $args['farm_id'] < 1) {
                return ['error' => 'Parameter farm_id tidak valid.'];
            }

            $farms = $farms->filter(fn (Farm $farm): bool => $farm->id === (int) $args['farm_id']);

            if ($farms->isEmpty()) {
                return ['error' => 'Farm tidak ditemukan atau Anda tidak memiliki akses.'];
            }
        }

        $overdueOnly = ! empty($args['overdue_only']);
        $service = app(ReceivableService::class);

        $payload = $farms
            ->map(function (Farm $farm) use ($service, $overdueOnly): array {
                $summary = $service->summary($farm);
                $status = $overdueOnly ? 'overdue' : null;
                $items = $service->receivables($farm, ['status' => $status, 'per_page' => 10])->items();

                return [
                    'farm_id' => $farm->id,
                    'farm_name' => $farm->name,
                    'total_remaining' => $summary['total_remaining'],
                    'customer_count' => $summary['customer_count'],
                    'overdue_count' => $summary['overdue_count'],
                    'receivables' => array_map(function (array $item): array {
                        return [
                            'customer' => $item['customer']['name'] ?? 'Pelanggan',
                            'remaining' => $item['remaining_amount'],
                            'due_date' => $item['due_date'],
                            'overdue' => $item['overdue'],
                        ];
                    }, $items),
                ];
            })
            ->values()
            ->all();

        return ['data' => count($payload) === 1 ? $payload[0] : $payload];
    }
}
