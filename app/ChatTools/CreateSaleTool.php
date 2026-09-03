<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\Customer;
use App\Models\Farm\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

class CreateSaleTool extends BaseTool
{
    public function name(): string
    {
        return 'create_sale';
    }

    public function description(): string
    {
        return 'Mencatat penjualan hasil panen ke warung/toko. Panggil tool ini saat pengguna bermaksud MENJUAL hasil panen ("jual", "terjual", "laku") BESERTA barang, jumlah, dan pembeli — misal "jual 3kg selada ke Warung Sari 60 ribu". Gunakan list_customers/list_products bila perlu untuk mendapatkan id. Isi customer_id bila pelanggan sudah terdaftar; jika pelanggan baru, isi customer_name (opsional customer_phone). Untuk tiap item isi product_id bila produk sudah terdaftar, atau product_name bila produk baru, beserta qty dan price per satuan. Bila pembayaran tidak lunas (kredit/hutang), isi due_date. Jangan panggil tool ini untuk transaksi non-penjualan seperti beli pupuk (gunakan create_financial_transaction).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm. Opsional jika user hanya punya satu farm.'],
                'customer_id' => ['type' => 'INTEGER', 'description' => 'ID pelanggan terdaftar (lihat list_customers).'],
                'customer_name' => ['type' => 'STRING', 'description' => 'Nama pelanggan baru bila belum terdaftar (mis. "Warung Sari").'],
                'customer_phone' => ['type' => 'STRING', 'description' => 'No HP pelanggan baru (opsional).'],
                'items' => [
                    'type' => 'ARRAY',
                    'description' => 'Daftar item terjual, minimal 1.',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'product_id' => ['type' => 'INTEGER', 'description' => 'ID produk terdaftar (lihat list_products).'],
                            'product_name' => ['type' => 'STRING', 'description' => 'Nama produk bila belum terdaftar (mis. "Selada").'],
                            'unit' => ['type' => 'STRING', 'description' => 'kg atau pcs. Default kg.'],
                            'qty' => ['type' => 'NUMBER', 'description' => 'Jumlah > 0.'],
                            'price' => ['type' => 'NUMBER', 'description' => 'Harga per satuan dalam Rupiah (mis. 21000).'],
                        ],
                        'required' => ['qty', 'price'],
                    ],
                ],
                'due_date' => ['type' => 'STRING', 'description' => 'Tanggal jatuh tempo YYYY-MM-DD bila penjualan kredit (belum lunas). Kosongkan bila lunas.'],
                'sale_date' => ['type' => 'STRING', 'description' => 'Tanggal jual YYYY-MM-DD. Default hari ini.'],
                'note' => ['type' => 'STRING', 'description' => 'Catatan opsional.'],
            ],
            'required' => ['items'],
        ];
    }

    /**
     * Validasi & normalisasi payload penjualan dari pesan natural.
     * Belum menulis DB; ProcessTelegramUpdate akan membuat pending + konfirmasi.
     *
     * @return array{data: array<string, mixed>}|array{error: string}
     */
    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user);

        if ($farms->isEmpty()) {
            return ['error' => 'NO_FARM', 'message' => 'Anda belum tergabung di farm mana pun.'];
        }

        $farmId = $this->resolveFarmId($args, $farms);
        if (is_array($farmId)) {
            return $farmId;
        }

        $farm = $farms->firstWhere('id', $farmId);

        $customer = $this->resolveCustomer($args, $farm);
        if (isset($customer['error'])) {
            return $customer;
        }

        $items = $this->normalizeItems($args['items'] ?? [], $farm);
        if (isset($items['error'])) {
            return $items;
        }

        $saleDate = $this->resolveDate($args['sale_date'] ?? null, 'sale_date');
        if (is_array($saleDate)) {
            return $saleDate;
        }

        $dueDate = null;
        if (! empty($args['due_date'])) {
            $dueDate = $this->resolveDate($args['due_date'], 'due_date');
            if (is_array($dueDate)) {
                return $dueDate;
            }

            if ($dueDate < $saleDate) {
                return ['error' => 'DATE_INVALID', 'message' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal jual.'];
            }
        }

        $total = round(array_sum(array_column($items, 'subtotal')), 2);

        return ['data' => [
            'farm_id' => $farm->id,
            'customer' => $customer,
            'items' => $items,
            'sale_date' => $saleDate,
            'due_date' => $dueDate,
            'total' => $total,
            'note' => isset($args['note']) && trim((string) $args['note']) !== '' ? trim((string) $args['note']) : null,
        ]];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Farm>  $farms
     * @return int|array{error: string, message?: string, farms?: array<int, array{id: int, name: string}>}
     */
    private function resolveFarmId(array $args, $farms): int|array
    {
        if (isset($args['farm_id']) && $args['farm_id'] !== null) {
            if (! is_numeric($args['farm_id']) || (int) $args['farm_id'] < 1) {
                return ['error' => 'FARM_INVALID', 'message' => 'Parameter farm_id tidak valid.'];
            }

            $farm = $farms->firstWhere('id', (int) $args['farm_id']);

            if (! $farm) {
                return ['error' => 'FARM_INVALID', 'message' => 'Farm tidak ditemukan atau Anda tidak memiliki akses.'];
            }

            return (int) $farm->id;
        }

        if ($farms->count() > 1) {
            return [
                'error' => 'FARM_REQUIRED',
                'message' => 'Pilih farm dulu.',
                'farms' => $farms->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all(),
            ];
        }

        return (int) $farms->first()->id;
    }

    private function resolveCustomer(array $args, Farm $farm): array
    {
        if (! empty($args['customer_id'])) {
            $customer = Customer::query()
                ->where('id', (int) $args['customer_id'])
                ->where('farm_id', $farm->id)
                ->where('is_active', true)
                ->first();

            if (! $customer) {
                return ['error' => 'CUSTOMER_INVALID', 'message' => 'Pelanggan tidak ditemukan untuk farm ini. Gunakan list_customers atau berikan customer_name untuk pelanggan baru.'];
            }

            return ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone];
        }

        $name = isset($args['customer_name']) ? trim((string) $args['customer_name']) : '';

        if ($name === '') {
            return ['error' => 'CUSTOMER_NEEDED', 'message' => 'Pilih pelanggan: gunakan customer_id dari list_customers, atau berikan customer_name untuk pelanggan baru.'];
        }

        return [
            'id' => null,
            'name' => $name,
            'phone' => isset($args['customer_phone']) && trim((string) $args['customer_phone']) !== ''
                ? trim((string) $args['customer_phone'])
                : null,
        ];
    }

    private function normalizeItems(array $rawItems, Farm $farm): array
    {
        if ($rawItems === []) {
            return ['error' => 'ITEM_REQUIRED', 'message' => 'Penjualan minimal memiliki satu item.'];
        }

        $result = [];

        foreach ($rawItems as $raw) {
            $qty = (float) ($raw['qty'] ?? 0);
            $price = (float) ($raw['price'] ?? 0);

            if ($qty <= 0) {
                return ['error' => 'ITEM_INVALID', 'message' => 'Qty item harus lebih dari 0.'];
            }

            if ($price < 0) {
                return ['error' => 'ITEM_INVALID', 'message' => 'Harga item tidak valid.'];
            }

            $unit = strtolower((string) ($raw['unit'] ?? 'kg'));
            if (! in_array($unit, ['kg', 'pcs'], true)) {
                return ['error' => 'ITEM_INVALID', 'message' => 'Satuan item harus kg atau pcs.'];
            }

            $productId = isset($raw['product_id']) && $raw['product_id'] !== null ? (int) $raw['product_id'] : null;
            $productName = trim((string) ($raw['product_name'] ?? ''));

            if ($productId !== null) {
                $product = Product::query()
                    ->where('id', $productId)
                    ->where('farm_id', $farm->id)
                    ->where('is_active', true)
                    ->first();

                if (! $product) {
                    return ['error' => 'PRODUCT_INVALID', 'message' => 'Produk tidak ditemukan untuk farm ini.'];
                }

                $productName = $product->name;
            }

            if ($productName === '') {
                return ['error' => 'ITEM_INVALID', 'message' => 'Nama produk wajib diisi bila tanpa product_id.'];
            }

            $result[] = [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'qty' => $qty,
                'price' => $price,
                'subtotal' => round($qty * $price, 2),
            ];
        }

        return $result;
    }

    private function resolveDate(?string $value, string $field): string|array
    {
        if ($value === null || trim($value) === '') {
            if ($field === 'sale_date') {
                return now()->toDateString();
            }

            return '';
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return ['error' => 'DATE_INVALID', 'message' => 'Format tanggal '.$field.' tidak valid (YYYY-MM-DD).'];
        }

        if ($field === 'sale_date' && $date->isFuture()) {
            return ['error' => 'DATE_FUTURE', 'message' => 'Tanggal jual tidak boleh di masa depan.'];
        }

        return $date->toDateString();
    }
}
