<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FinancialTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'type' => 'nullable|in:income,expense',
            'category_id' => 'nullable|integer|exists:financial_categories,id',
            'search' => 'nullable|string|max:100',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewFinance', $farm);

        $transactions = FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('transaction_date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('transaction_date', '<=', $to))
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($validated['category_id'] ?? null, fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where('note', 'ilike', "%{$search}%"))
            ->with(['category', 'user'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->paginatedResponse($transactions);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewFinance', $farm);

        $from = Carbon::parse($validated['from'] ?? now()->subDays(29)->startOfDay());
        $to = Carbon::parse($validated['to'] ?? now()->endOfDay());

        $summary = app(FinanceService::class)->summary(
            $farm,
            $from,
            $to,
            $validated['group_by'] ?? 'day',
        );

        return $this->successResponse(['summary' => $summary]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageFinance', $farm);

        $category = $this->resolveCategory($farm, $validated['category_id'], $validated['type']);
        if (! $category instanceof FinancialCategory) {
            return $category;
        }

        $transaction = FinancialTransaction::create($validated + [
            'user_id' => $request->user()->id,
            'source' => 'manual',
            'status' => 'approved',
        ]);

        return $this->successResponse(
            ['transaction' => $transaction->load('category')],
            'Transaksi berhasil disimpan.',
            201,
        );
    }

    public function show(FinancialTransaction $financialTransaction): JsonResponse
    {
        $farm = Farm::findOrFail($financialTransaction->farm_id);
        $this->authorize('viewFinance', $farm);

        return $this->successResponse(['transaction' => $financialTransaction->load(['category', 'user'])]);
    }

    public function update(Request $request, FinancialTransaction $financialTransaction): JsonResponse
    {
        $farm = Farm::findOrFail($financialTransaction->farm_id);
        $this->authorize('manageFinance', $farm);

        $validated = $this->validatePayload($request);
        unset($validated['farm_id']);
        $category = $this->resolveCategory($farm, $validated['category_id'], $validated['type']);
        if (! $category instanceof FinancialCategory) {
            return $category;
        }

        $financialTransaction->update($validated);

        return $this->successResponse(
            ['transaction' => $financialTransaction->fresh(['category'])],
            'Transaksi berhasil diperbarui.',
        );
    }

    public function destroy(FinancialTransaction $financialTransaction): JsonResponse
    {
        $farm = Farm::findOrFail($financialTransaction->farm_id);
        $this->authorize('manageFinance', $farm);

        $financialTransaction->delete();

        return $this->successResponse(null, 'Transaksi berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'category_id' => [
                'required', 'integer',
                Rule::exists('financial_categories', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01|max:99999999999.99',
            'transaction_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:1000',
        ]);
    }

    private function resolveCategory(Farm $farm, int $categoryId, string $type): JsonResponse|FinancialCategory
    {
        $category = FinancialCategory::query()
            ->forFarm($farm->id)
            ->whereKey($categoryId)
            ->first();

        if ($category === null || ! $category->is_active) {
            return $this->errorResponse('Kategori tidak ditemukan atau tidak aktif.', 422, [
                'category_id' => ['Kategori tidak ditemukan atau tidak aktif.'],
            ]);
        }

        if ($category->type !== $type) {
            return $this->errorResponse('Tipe kategori tidak sesuai.', 422, [
                'category_id' => ['Tipe kategori tidak sesuai dengan tipe transaksi.'],
            ]);
        }

        return $category;
    }
}
