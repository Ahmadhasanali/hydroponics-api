<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);
        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('viewFinance', $farm);

        $categories = FinancialCategory::query()
            ->forFarm($farm->id)
            ->orderBy('farm_id')
            ->orderBy('name')
            ->get();

        return $this->successResponse(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|integer|exists:farms,id',
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
        ]);

        $farm = Farm::findOrFail($validated['farm_id']);
        $this->authorize('manageFinance', $farm);

        $duplicate = FinancialCategory::query()
            ->forFarm($farm->id)
            ->where('type', $validated['type'])
            ->where('name', $validated['name'])
            ->exists();

        if ($duplicate) {
            return $this->errorResponse('Kategori dengan nama tersebut sudah ada.', 422, [
                'name' => ['Kategori dengan nama tersebut sudah ada.'],
            ]);
        }

        $category = FinancialCategory::create($validated + ['is_default' => false]);

        return $this->successResponse(['category' => $category], 'Kategori berhasil ditambahkan.', 201);
    }

    public function update(Request $request, FinancialCategory $financialCategory): JsonResponse
    {
        abort_if($financialCategory->farm_id === null, 404);

        $validated = $request->validate(['name' => 'required|string|max:100']);
        $farm = Farm::findOrFail($financialCategory->farm_id);
        $this->authorize('manageFinance', $farm);

        $financialCategory->update(['name' => $validated['name']]);

        return $this->successResponse(['category' => $financialCategory], 'Kategori berhasil diperbarui.');
    }

    public function destroy(FinancialCategory $financialCategory): JsonResponse
    {
        abort_if($financialCategory->farm_id === null, 404);

        $farm = Farm::findOrFail($financialCategory->farm_id);
        $this->authorize('manageFinance', $farm);

        $financialCategory->update(['is_active' => false]);

        return $this->successResponse(null, 'Kategori dinonaktifkan.');
    }
}
