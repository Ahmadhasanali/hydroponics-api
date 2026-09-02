<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\User;
use Illuminate\Support\Carbon;

class CreateTransactionTool extends BaseTool
{
    public function name(): string
    {
        return 'create_financial_transaction';
    }

    public function description(): string
    {
        return 'Buat transaksi keuangan farm dari pesan natural. Wajib farm_id jika user punya >1 farm. Validasi kategori milik farm.';
    }

    public function parameters(): array
    {
        return ['type' => 'OBJECT', 'properties' => [
            'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm. Wajib jika user punya >1 farm.'],
            'type' => ['type' => 'STRING', 'description' => 'income atau expense'],
            'category_id' => ['type' => 'INTEGER', 'description' => 'ID kategori'],
            'amount' => ['type' => 'NUMBER', 'description' => 'Nominal Rp >0'],
            'transaction_date' => ['type' => 'STRING', 'description' => 'YYYY-MM-DD, default hari ini'],
            'note' => ['type' => 'STRING', 'description' => 'Catatan opsional'],
        ], 'required' => ['type', 'category_id', 'amount']];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user);

        if ($farms->isEmpty()) {
            return ['error' => 'NO_FARM', 'message' => 'Anda belum tergabung di farm mana pun.'];
        }

        $farmId = null;

        if (array_key_exists('farm_id', $args) && $args['farm_id'] !== null) {
            if (! is_numeric($args['farm_id']) || (int) $args['farm_id'] < 1) {
                return ['error' => 'FARM_INVALID', 'message' => 'farm_id tidak valid'];
            }

            $farmId = (int) $args['farm_id'];

            $farms = $farms->filter(fn (Farm $f) => $f->id === $farmId);

            if ($farms->isEmpty()) {
                return ['error' => 'FARM_REQUIRED', 'message' => 'Farm tidak ditemukan atau tidak punya akses.'];
            }
        } else {
            if ($farms->count() > 1) {
                return ['error' => 'FARM_REQUIRED', 'message' => 'Pilih farm dulu.', 'farms' => $farms->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all()];
            }

            $farmId = $farms->first()->id;
        }

        $type = $args['type'] ?? null;

        if (! in_array($type, ['income', 'expense'], true)) {
            return ['error' => 'TYPE_INVALID', 'message' => 'type harus income atau expense'];
        }

        if (! isset($args['category_id']) || ! is_numeric($args['category_id'])) {
            return ['error' => 'CATEGORY_INVALID', 'message' => 'category_id wajib'];
        }

        $category = FinancialCategory::forFarm($farmId)->whereKey((int) $args['category_id'])->first();

        if (! $category || ! $category->is_active) {
            return ['error' => 'CATEGORY_INVALID', 'message' => 'Kategori tidak ditemukan atau tidak aktif.'];
        }

        if ($category->type !== $type) {
            return ['error' => 'TYPE_MISMATCH', 'message' => 'Tipe kategori tidak sesuai.'];
        }

        $amount = $args['amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 99999999999.99) {
            return ['error' => 'AMOUNT_INVALID', 'message' => 'amount harus >0 dan <= 99999999999.99'];
        }

        $dateStr = $args['transaction_date'] ?? now()->toDateString();

        try {
            $date = Carbon::parse($dateStr);
        } catch (\Throwable) {
            return ['error' => 'DATE_INVALID', 'message' => 'Format tanggal YYYY-MM-DD tidak valid'];
        }

        if ($date->isFuture()) {
            return ['error' => 'DATE_FUTURE', 'message' => 'Tanggal tidak boleh di masa depan'];
        }

        return ['data' => ['farm_id' => $farmId, 'type' => $type, 'category_id' => $category->id, 'amount' => (float) $amount, 'transaction_date' => $date->toDateString(), 'note' => $args['note'] ?? null]];
    }
}
