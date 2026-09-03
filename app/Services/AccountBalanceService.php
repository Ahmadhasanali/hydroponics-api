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
     * Uses firstOrCreate for race safety; DB-level partial unique index deferred.
     * TODO(deferred): migration partial unique index WHERE is_default = true per farm_id.
     */
    public function ensureDefaultAccount(int $farmId): Account
    {
        return Account::firstOrCreate(
            ['farm_id' => $farmId, 'is_default' => true],
            ['name' => 'Cash', 'type' => 'cash', 'balance_initial' => 0, 'is_active' => true]
        );
    }
}
