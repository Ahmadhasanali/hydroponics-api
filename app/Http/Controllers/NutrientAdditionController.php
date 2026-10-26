<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NutrientAdditionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->integer('farm_id');

        if (! $farmId) {
            return $this->errorResponse('farm_id is required.', 422);
        }

        Gate::authorize('view', Farm::findOrFail($farmId));

        $tankIds = Tank::where('farm_id', $farmId)->pluck('id');
        $additions = NutrientAddition::whereIn('tank_id', $tankIds)
            ->with(['tank', 'user'])
            ->latest('log_date')
            ->paginate(20);

        return $this->paginatedResponse($additions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm_before' => 'required|numeric|min:0|max:3000',
            'ppm_after' => 'required|numeric|min:0|max:3000|gt:ppm_before',
            'nutrient_a_ml' => 'required|numeric|min:0|max:10000',
            'nutrient_b_ml' => 'required|numeric|min:0|max:10000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::findOrFail($validated['tank_id']);
        Gate::authorize('update', $tank->farm);

        $addition = NutrientAddition::create($validated + ['user_id' => $request->user()->id]);

        return $this->successResponse(['nutrient' => $addition], 'Data AB Mix berhasil disimpan.', 201);
    }

    public function show(NutrientAddition $nutrientAddition): JsonResponse
    {
        Gate::authorize('view', $nutrientAddition->tank->farm);

        $nutrientAddition->load(['tank', 'user']);

        return $this->successResponse(['nutrient' => $nutrientAddition]);
    }

    public function update(Request $request, NutrientAddition $nutrientAddition): JsonResponse
    {
        Gate::authorize('update', $nutrientAddition->tank->farm);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm_before' => 'required|numeric|min:0|max:3000',
            'ppm_after' => 'required|numeric|min:0|max:3000|gt:ppm_before',
            'nutrient_a_ml' => 'required|numeric|min:0|max:10000',
            'nutrient_b_ml' => 'required|numeric|min:0|max:10000',
            'notes' => 'nullable|string|max:1000',
        ]);

        Tank::where('farm_id', $nutrientAddition->tank->farm_id)
            ->findOrFail($validated['tank_id']);

        $nutrientAddition->update($validated);

        return $this->successResponse(['nutrient' => $nutrientAddition], 'Data AB Mix berhasil diperbarui.');
    }

    public function destroy(NutrientAddition $nutrientAddition): JsonResponse
    {
        Gate::authorize('update', $nutrientAddition->tank->farm);

        $nutrientAddition->delete();

        return $this->successResponse(null, 'Data AB Mix berhasil dihapus.');
    }
}
