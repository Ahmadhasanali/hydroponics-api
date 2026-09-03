<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farm\Sale;
use App\Services\ReceivableService;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SalesService $sales,
        private readonly ReceivableService $receivables,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        $sales = Sale::query()
            ->where('farm_id', $farm->id)
            ->with(['customer', 'items', 'payments'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page', $validated['page'] ?? 1)
            ->through(function (Sale $sale): array {
                return $sale->toArray() + [
                    'status' => $this->sales->status($sale),
                    'paid_amount' => $this->sales->paidAmount($sale),
                    'remaining_amount' => $this->sales->remaining($sale),
                ];
            });

        return $this->paginatedResponse($sales);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageSales', $farm);

        try {
            $sale = $this->sales->createSale($request->user(), $farm, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(
            ['sale' => $sale->load(['customer', 'items', 'payments'])],
            'Penjualan berhasil disimpan.',
            201,
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        $farm = Farm::findOrFail($sale->farm_id);
        $this->authorize('viewSales', $farm);

        $sale->load(['customer', 'items', 'payments.account']);

        return $this->successResponse(['sale' => $sale->toArray() + [
            'status' => $this->sales->status($sale),
            'paid_amount' => $this->sales->paidAmount($sale),
            'remaining_amount' => $this->sales->remaining($sale),
        ]]);
    }

    public function update(Request $request, Sale $sale): JsonResponse
    {
        $farm = Farm::findOrFail($sale->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'sale_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:sale_date',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->sales->updateSale($request->user(), $sale, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(['sale' => $sale->fresh(['customer', 'items', 'payments'])], 'Penjualan berhasil diperbarui.');
    }

    public function destroy(Request $request, Sale $sale): JsonResponse
    {
        $farm = Farm::findOrFail($sale->farm_id);
        $this->authorize('manageSales', $farm);

        $this->sales->cancelSale($request->user(), $sale);

        return $this->successResponse(null, 'Penjualan dibatalkan.');
    }

    public function receivables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'status' => 'nullable|in:overdue',
            'page' => 'nullable|integer|min:1',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        return $this->paginatedResponse($this->receivables->receivables($farm, $validated));
    }

    public function receivableSummary(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        return $this->successResponse(['summary' => $this->receivables->summary($farm)]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'sale_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:sale_date',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'required_without:items.*.product_id|string|max:255',
            'items.*.unit' => 'required|in:kg,pcs',
            'items.*.qty' => 'required|numeric|min:0.01|max:99999999.99',
            'items.*.price' => 'required|numeric|min:0|max:9999999999.99',
            'account_id' => 'nullable|integer|exists:accounts,id|required_with:amount_paid',
            'amount_paid' => 'nullable|numeric|min:0.01|max:9999999999.99|required_with:account_id',
        ]);
    }
}
