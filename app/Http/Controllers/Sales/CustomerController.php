<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farm\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        $customers = Customer::query()
            ->where('farm_id', $farm->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse(['customers' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageSales', $farm);

        $customer = Customer::create($validated);

        return $this->successResponse(['customer' => $customer], 'Pelanggan berhasil ditambahkan.', 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $farm = Farm::findOrFail($customer->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
        ]);

        $customer->update($validated);

        return $this->successResponse(['customer' => $customer->fresh()], 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $farm = Farm::findOrFail($customer->farm_id);
        $this->authorize('manageSales', $farm);

        $customer->update(['is_active' => false]);

        return $this->successResponse(null, 'Pelanggan dinonaktifkan.');
    }
}
