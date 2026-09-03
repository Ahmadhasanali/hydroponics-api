<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farm\Payment;
use App\Models\Farm\Sale;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $farm = Farm::findOrFail($sale->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01|max:9999999999.99',
            'payment_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $this->sales->registerPayment($request->user(), $sale, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(
            ['payment' => $payment->load('account')],
            'Pembayaran berhasil dicatat.',
            201,
        );
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        $farm = Farm::findOrFail($payment->sale->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01|max:9999999999.99',
            'payment_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->sales->updatePayment($request->user(), $payment, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(['payment' => $payment->fresh('account')], 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $farm = Farm::findOrFail($payment->sale->farm_id);
        $this->authorize('manageSales', $farm);

        $this->sales->deletePayment(request()->user(), $payment);

        return $this->successResponse(null, 'Pembayaran dihapus.');
    }
}
