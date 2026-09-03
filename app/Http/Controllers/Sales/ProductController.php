<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farm\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewSales', $farm);

        $products = Product::query()
            ->where('farm_id', $farm->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse(['products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'name' => 'required|string|max:255',
            'unit' => 'required|in:kg,pcs',
            'default_price' => 'required|numeric|min:0|max:9999999999.99',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageSales', $farm);

        $product = Product::create($validated);

        return $this->successResponse(['product' => $product], 'Produk berhasil ditambahkan.', 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $farm = Farm::findOrFail($product->farm_id);
        $this->authorize('manageSales', $farm);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|in:kg,pcs',
            'default_price' => 'required|numeric|min:0|max:9999999999.99',
        ]);

        $product->update($validated);

        return $this->successResponse(['product' => $product->fresh()], 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $farm = Farm::findOrFail($product->farm_id);
        $this->authorize('manageSales', $farm);

        $product->update(['is_active' => false]);

        return $this->successResponse(null, 'Produk dinonaktifkan.');
    }
}
