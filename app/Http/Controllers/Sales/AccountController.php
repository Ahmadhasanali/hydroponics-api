<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\AccountBalanceAdjustment;
use App\Services\AccountBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly AccountBalanceService $balanceService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        // TODO(deferred MVP): N+1 balance — 5 SUMs per account. Intentional for small account counts (<20/farm).
        // If scaling needed, batch query payments/financial_transactions/transfers/adjustments grouped by account.
        $accounts = Account::query()
            ->where('farm_id', $farm->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => $account->toArray() + [
                'balance' => $this->balanceService->balance($account),
            ]);

        return $this->successResponse(['accounts' => $accounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,ewallet,bank',
            'balance_initial' => 'nullable|numeric|min:0|max:9999999999.99',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageSales', $farm);

        $account = Account::create([
            'farm_id' => $farm->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'balance_initial' => $validated['balance_initial'] ?? 0,
            'is_default' => false,
            'is_active' => true,
        ]);

        return $this->successResponse(['account' => $account], 'Akun berhasil ditambahkan.', 201);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $farm = Farm::findOrFail($account->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,ewallet,bank',
        ]);

        $account->update($validated);

        return $this->successResponse(['account' => $account->fresh()], 'Akun berhasil diperbarui.');
    }

    public function destroy(Account $account): JsonResponse
    {
        $farm = Farm::findOrFail($account->farm_id);
        $this->authorize('manageSales', $farm);

        if ($account->is_default) {
            return $this->errorResponse('Akun default tidak dapat dinonaktifkan.', 422);
        }

        $account->update(['is_active' => false]);

        return $this->successResponse(null, 'Akun dinonaktifkan.');
    }

    public function balance(Account $account): JsonResponse
    {
        $farm = Farm::findOrFail($account->farm_id);
        $this->authorize('viewSales', $farm);

        return $this->successResponse([
            'account_id' => $account->id,
            'balance' => $this->balanceService->balance($account),
        ]);
    }

    public function adjustments(Account $account): JsonResponse
    {
        $farm = Farm::findOrFail($account->farm_id);
        $this->authorize('viewSales', $farm);

        $items = AccountBalanceAdjustment::query()
            ->where('account_id', $account->id)
            ->orderByDesc('adjustment_date')
            ->get();

        return $this->successResponse(['adjustments' => $items]);
    }

    public function storeAdjustment(Request $request, Account $account): JsonResponse
    {
        $farm = Farm::findOrFail($account->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0|min:-9999999999.99|max:9999999999.99',
            'adjustment_date' => 'required|date|before_or_equal:today',
            'reason' => 'required|string|max:500',
        ]);

        $adjustment = AccountBalanceAdjustment::create($validated + [
            'farm_id' => $farm->id,
            'account_id' => $account->id,
            'user_id' => $request->user()->id,
        ]);

        return $this->successResponse(
            ['adjustment' => $adjustment],
            'Saldo berhasil disesuaikan.',
            201,
        );
    }
}
