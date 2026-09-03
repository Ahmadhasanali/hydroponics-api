<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\Customer;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Models\Farm\Payment;
use App\Models\Farm\Product;
use App\Models\Farm\Sale;
use App\Models\Farm\SaleFinancialLink;
use App\Models\Farm\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesService
{
    public function createSale(User $user, Farm $farm, array $payload): Sale
    {
        $items = $this->normalizeItems($payload['items'] ?? []);
        if ($items === []) {
            throw new InvalidArgumentException('Penjualan minimal memiliki satu item.');
        }

        $this->assertCustomerInFarm($farm, (int) $payload['customer_id']);
        $this->assertProductsInFarm($farm, $items);

        $total = array_sum(array_column($items, 'subtotal'));

        return DB::transaction(function () use ($user, $farm, $payload, $items, $total): Sale {
            $sale = Sale::create([
                'farm_id' => $farm->id,
                'customer_id' => $payload['customer_id'],
                'sale_date' => $payload['sale_date'],
                'due_date' => $payload['due_date'] ?? null,
                'total_amount' => $total,
                'note' => $payload['note'] ?? null,
                'user_id' => $user->id,
            ]);

            foreach ($items as $item) {
                SaleItem::create($item + ['sale_id' => $sale->id]);
            }

            $amountPaid = isset($payload['amount_paid']) ? (float) $payload['amount_paid'] : 0.0;
            if ($amountPaid > 0) {
                $this->assertAccount($farm, (int) $payload['account_id']);
                $this->registerPayment($user, $sale, [
                    'account_id' => (int) $payload['account_id'],
                    'amount' => $amountPaid,
                    'payment_date' => $payload['sale_date'],
                    'note' => 'Pembayaran saat penjualan',
                ], allowTotalCheck: false);
            }

            app(SaleReminderService::class)->createForSale($sale, $user);

            return $sale->load(['customer', 'items', 'payments']);
        });
    }

    public function registerPayment(User $user, Sale $sale, array $payload, bool $allowTotalCheck = true): Payment
    {
        if ($allowTotalCheck && $sale->total_amount <= 0) {
            throw new InvalidArgumentException('Total penjualan tidak valid.');
        }

        $farm = $sale->farm ?? Farm::findOrFail($sale->farm_id);
        $this->assertAccount($farm, (int) $payload['account_id']);

        return DB::transaction(function () use ($user, $sale, $payload): Payment {
            $amount = (float) $payload['amount'];

            if ($amount <= 0) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
            }

            $remaining = (float) $sale->total_amount - $this->paidAmount($sale);
            if ($amount > $remaining + 0.0001) {
                throw new InvalidArgumentException('Pembayaran melebihi sisa piutang.');
            }

            $payment = Payment::create([
                'sale_id' => $sale->id,
                'account_id' => $payload['account_id'],
                'amount' => $amount,
                'payment_date' => $payload['payment_date'],
                'note' => $payload['note'] ?? null,
                'user_id' => $user->id,
            ]);

            $this->syncFinancialTransaction($user, $payment);

            app(SaleReminderService::class)->markDoneIfPaid($sale->fresh());

            return $payment;
        });
    }

    public function updatePayment(User $user, Payment $payment, array $payload): Payment
    {
        $sale = $payment->sale;
        if (! $sale) {
            $sale = $payment->sale()->firstOrFail();
        }
        $farm = $sale->farm ?? Farm::findOrFail($sale->farm_id);
        $this->assertAccount($farm, (int) $payload['account_id']);

        return DB::transaction(function () use ($user, $sale, $payment, $payload): Payment {
            $amount = (float) $payload['amount'];
            if ($amount <= 0) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
            }

            $otherPaid = $this->paidAmount($sale) - (float) $payment->amount;
            if ($amount > (float) $sale->total_amount - $otherPaid + 0.0001) {
                throw new InvalidArgumentException('Total pembayaran melebihi total penjualan.');
            }

            $payment->update([
                'account_id' => $payload['account_id'],
                'amount' => $amount,
                'payment_date' => $payload['payment_date'],
                'note' => $payload['note'] ?? null,
            ]);

            $this->syncFinancialTransaction($user, $payment->fresh());

            return $payment->fresh();
        });
    }

    public function deletePayment(User $user, Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            SaleFinancialLink::query()
                ->where('linkable_type', Payment::class)
                ->where('linkable_id', $payment->id)
                ->get()
                ->each(function (SaleFinancialLink $link): void {
                    $link->financialTransaction?->delete();
                    $link->delete();
                });

            $payment->delete();
        });
    }

    public function cancelSale(User $user, Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale->payments()->get()->each(function (Payment $payment): void {
                SaleFinancialLink::query()
                    ->where('linkable_type', Payment::class)
                    ->where('linkable_id', $payment->id)
                    ->get()
                    ->each(function (SaleFinancialLink $link): void {
                        $link->financialTransaction?->delete();
                        $link->delete();
                    });
                $payment->delete();
            });

            $sale->delete();

            app(SaleReminderService::class)->deactivateForSale($sale);
        });
    }

    public function updateSale(User $user, Sale $sale, array $payload): Sale
    {
        $paid = $this->paidAmount($sale);
        $farm = $sale->farm ?? Farm::findOrFail($sale->farm_id);

        if (isset($payload['customer_id'])) {
            $this->assertCustomerInFarm($farm, (int) $payload['customer_id']);
        }

        if (isset($payload['items'])) {
            $normalizedForCheck = $this->normalizeItems($payload['items']);
            if ($normalizedForCheck === []) {
                throw new InvalidArgumentException('Penjualan minimal memiliki satu item.');
            }
            $this->assertProductsInFarm($farm, $normalizedForCheck);
        }

        $updated = DB::transaction(function () use ($sale, $payload, $paid): Sale {
            if (isset($payload['items'])) {
                $newTotal = array_sum(array_column($this->normalizeItems($payload['items']), 'subtotal'));
                if ($paid > $newTotal + 0.0001) {
                    throw new InvalidArgumentException('Total baru lebih kecil dari yang sudah dibayar.');
                }
            }

            $sale->update([
                'customer_id' => $payload['customer_id'] ?? $sale->customer_id,
                'sale_date' => $payload['sale_date'] ?? $sale->sale_date->toDateString(),
                'due_date' => array_key_exists('due_date', $payload) ? $payload['due_date'] : $sale->due_date?->toDateString(),
                'note' => $payload['note'] ?? $sale->note,
            ]);

            if (isset($payload['items'])) {
                $sale->items()->delete();
                foreach ($this->normalizeItems($payload['items']) as $item) {
                    SaleItem::create($item + ['sale_id' => $sale->id]);
                }
                $sale->update(['total_amount' => array_sum(array_column($this->normalizeItems($payload['items']), 'subtotal'))]);
            }

            return $sale->fresh(['customer', 'items', 'payments']);
        });

        app(SaleReminderService::class)->syncAfterSaleUpdate($updated->fresh());

        return $updated;
    }

    public function paidAmount(Sale $sale): float
    {
        return round((float) $sale->payments()->sum('amount'), 2);
    }

    public function status(Sale $sale): string
    {
        $total = (float) $sale->total_amount;
        $paid = $this->paidAmount($sale);

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total - 0.0001 ? 'paid' : 'partial';
    }

    public function remaining(Sale $sale): float
    {
        return round((float) $sale->total_amount - $this->paidAmount($sale), 2);
    }

    /**
     * @return list<array{product_id?: int, product_name: string, unit: string, qty: float, price: float, subtotal: float}>
     */
    private function normalizeItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $unit = $item['unit'] ?? null;
            if (! in_array($unit, ['kg', 'pcs'], true)) {
                throw new InvalidArgumentException('Satuan item tidak valid.');
            }

            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            if ($qty <= 0) {
                throw new InvalidArgumentException('Qty item harus lebih dari 0.');
            }

            if ($price < 0) {
                throw new InvalidArgumentException('Harga item tidak valid.');
            }

            $rawName = trim((string) ($item['product_name'] ?? ''));
            $productId = isset($item['product_id']) && $item['product_id'] !== null ? (int) $item['product_id'] : null;
            if ($rawName === '' && $productId !== null) {
                $product = Product::find($productId);
                if (! $product) {
                    throw new InvalidArgumentException('Produk tidak ditemukan.');
                }
                $rawName = $product->name;
            }
            if ($productId === null && $rawName === '') {
                throw new InvalidArgumentException('Nama produk wajib diisi bila tanpa product_id.');
            }

            $result[] = [
                'product_id' => $productId,
                'product_name' => $rawName,
                'unit' => (string) $unit,
                'qty' => $qty,
                'price' => $price,
                'subtotal' => round($qty * $price, 2),
            ];
        }

        return $result;
    }

    private function assertAccount(Farm $farm, int $accountId): void
    {
        $exists = Account::query()
            ->where('id', $accountId)
            ->where('farm_id', $farm->id)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Akun tidak valid.');
        }
    }

    private function assertCustomerInFarm(Farm $farm, int $customerId): void
    {
        $exists = Customer::query()
            ->where('id', $customerId)
            ->where('farm_id', $farm->id)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Pelanggan tidak valid untuk farm ini.');
        }
    }

    private function assertProductsInFarm(Farm $farm, array $items): void
    {
        $productIds = array_values(array_filter(array_map(
            fn ($item) => $item['product_id'] ?? null,
            $items,
        )));

        if ($productIds === []) {
            return;
        }

        $validCount = Product::query()
            ->where('farm_id', $farm->id)
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->count();

        if ($validCount !== count(array_unique($productIds))) {
            throw new InvalidArgumentException('Produk tidak valid untuk farm ini.');
        }
    }

    private function syncFinancialTransaction(User $user, Payment $payment): void
    {
        $farmId = $payment->sale->farm_id;
        // Race-safe: firstOrCreate avoids duplicate global category under concurrency.
        // TODO(deferred): add partial unique index (farm_id IS NULL, name, type) via migration to enforce at DB level.
        $category = FinancialCategory::firstOrCreate(
            ['farm_id' => null, 'name' => 'Penjualan Panen', 'type' => 'income'],
            ['is_default' => true, 'is_active' => true]
        );

        $link = SaleFinancialLink::query()
            ->where('linkable_type', Payment::class)
            ->where('linkable_id', $payment->id)
            ->first();

        if ($link) {
            $link->financialTransaction->update([
                'amount' => $payment->amount,
                'transaction_date' => $payment->payment_date->toDateString(),
                'account_id' => $payment->account_id,
                'note' => $payment->note ?? 'Pembayaran piutang penjualan',
            ]);

            return;
        }

        $transaction = FinancialTransaction::create([
            'farm_id' => $farmId,
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => $payment->amount,
            'transaction_date' => $payment->payment_date->toDateString(),
            'source' => 'sale',
            'status' => 'approved',
            'account_id' => $payment->account_id,
            'user_id' => $user->id,
            'note' => $payment->note ?? 'Pembayaran piutang penjualan',
        ]);

        SaleFinancialLink::create([
            'farm_id' => $farmId,
            'financial_transaction_id' => $transaction->id,
            'linkable_type' => Payment::class,
            'linkable_id' => $payment->id,
        ]);
    }
}
