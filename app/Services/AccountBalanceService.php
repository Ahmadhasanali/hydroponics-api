<?php

namespace App\Services;

use App\Models\Farm\Account;
use App\Models\Farm\AccountBalanceAdjustment;
use App\Models\Farm\AccountTransfer;
use App\Models\Farm\FinancialTransaction;
use App\Models\Farm\Payment;

class AccountBalanceService
{
    public function balance(Account $account): float
    {
        $initial = (float) $account->balance_initial;

        $incoming = (float) Payment::query()
            ->where('account_id', $account->id)
            ->sum('amount');

        $expenses = (float) FinancialTransaction::query()
            ->where('account_id', $account->id)
            ->where('type', 'expense')
            ->sum('amount');

        $transferIn = (float) AccountTransfer::query()
            ->where('to_account_id', $account->id)
            ->sum('amount');

        $transferOut = (float) AccountTransfer::query()
            ->where('from_account_id', $account->id)
            ->sum('amount');

        $adjustments = (float) AccountBalanceAdjustment::query()
            ->where('account_id', $account->id)
            ->sum('amount');

        return round($initial + $incoming - $expenses + $transferIn - $transferOut + $adjustments, 2);
    }

    /**
     * Memastikan akun Cash default ada untuk farm (idempotent).
     */
    public function ensureDefaultAccount(int $farmId): Account
    {
        $existing = Account::query()
            ->where('farm_id', $farmId)
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Account::query()->create([
            'farm_id' => $farmId,
            'name' => 'Cash',
            'type' => 'cash',
            'balance_initial' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
