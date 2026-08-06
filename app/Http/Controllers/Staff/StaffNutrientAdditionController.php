<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffNutrientAdditionController extends Controller
{
    private function staff(): Staff
    {
        return request()->user();
    }

    private function farmTankIds()
    {
        return Tank::where('farm_id', $this->staff()->farm_id)->pluck('id');
    }

    public function index(): JsonResponse
    {
        $additions = NutrientAddition::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return $this->paginatedResponse($additions);
    }

    public function show(NutrientAddition $nutrientAddition): JsonResponse
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        return $this->successResponse(['nutrient' => $nutrientAddition->load('tank')]);
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

        abort_unless($this->farmTankIds()->contains($validated['tank_id']), 403);

        $nutrientAddition = NutrientAddition::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return $this->successResponse(['nutrient' => $nutrientAddition], 'Data AB Mix berhasil disimpan.', 201);
    }

    public function update(Request $request, NutrientAddition $nutrientAddition): JsonResponse
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm_before' => 'required|numeric|min:0|max:3000',
            'ppm_after' => 'required|numeric|min:0|max:3000|gt:ppm_before',
            'nutrient_a_ml' => 'required|numeric|min:0|max:10000',
            'nutrient_b_ml' => 'required|numeric|min:0|max:10000',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_unless($this->farmTankIds()->contains($validated['tank_id']), 403);

        $nutrientAddition->update($validated);

        return $this->successResponse(['nutrient' => $nutrientAddition], 'Data AB Mix berhasil diperbarui.');
    }

    public function destroy(NutrientAddition $nutrientAddition): JsonResponse
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        $nutrientAddition->delete();

        return $this->successResponse(null, 'Data AB Mix berhasil dihapus.');
    }
}
