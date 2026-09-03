<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\AccountTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        $transfers = AccountTransfer::query()
            ->where('farm_id', $farm->id)
            ->with(['fromAccount', 'toAccount'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->paginatedResponse($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'from_account_id' => 'required|integer|exists:accounts,id',
            'to_account_id' => 'required|integer|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01|max:9999999999.99',
            'transfer_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:1000',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageSales', $farm);

        // Pastikan kedua akun milik farm yang sama
        $validAccountIds = Account::query()
            ->where('farm_id', $farm->id)
            ->whereIn('id', [$validated['from_account_id'], $validated['to_account_id']])
            ->where('is_active', true)
            ->count();

        if ($validAccountIds !== 2) {
            return $this->errorResponse('Akun tidak ditemukan untuk farm ini.', 422, [
                'from_account_id' => ['Akun tidak valid.'],
                'to_account_id' => ['Akun tidak valid.'],
            ]);
        }

        if ($validated['from_account_id'] === $validated['to_account_id']) {
            return $this->errorResponse('Akun asal dan tujuan harus berbeda.', 422, [
                'to_account_id' => ['Akun asal dan tujuan harus berbeda.'],
            ]);
        }

        $transfer = AccountTransfer::create($validated + ['user_id' => $request->user()->id]);

        return $this->successResponse(['transfer' => $transfer->load(['fromAccount', 'toAccount'])], 'Transfer berhasil dicatat.', 201);
    }

    public function destroy(AccountTransfer $accountTransfer): JsonResponse
    {
        $farm = Farm::findOrFail($accountTransfer->farm_id);
        $this->authorize('manageSales', $farm);

        $accountTransfer->delete();

        return $this->successResponse(null, 'Transfer dihapus.');
    }
}
