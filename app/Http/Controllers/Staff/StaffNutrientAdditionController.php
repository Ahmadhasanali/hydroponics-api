<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffNutrientAdditionController extends Controller
{
    private function staff(): Staff
    {
        return auth('staff')->user();
    }

    private function farmTanks()
    {
        return Tank::where('farm_id', $this->staff()->farm_id)->orderBy('name')->get();
    }

    public function index(): View
    {
        $additions = NutrientAddition::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return view('staff.nutrient.index', compact('additions'));
    }

    public function create(): View
    {
        return view('staff.nutrient.create', ['tanks' => $this->farmTanks()]);
    }

    public function store(Request $request): RedirectResponse
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

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        NutrientAddition::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return redirect()->route('staff.nutrient.index')
            ->with('success', 'Data AB Mix berhasil disimpan.');
    }

    public function edit(NutrientAddition $nutrientAddition): View
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        return view('staff.nutrient.edit', [
            'nutrientAddition' => $nutrientAddition,
            'tanks' => $this->farmTanks(),
        ]);
    }

    public function update(Request $request, NutrientAddition $nutrientAddition): RedirectResponse
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

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $nutrientAddition->update($validated);

        return redirect()->route('staff.nutrient.index')
            ->with('success', 'Data AB Mix berhasil diperbarui.');
    }

    public function destroy(NutrientAddition $nutrientAddition): RedirectResponse
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        $nutrientAddition->delete();

        return redirect()->route('staff.nutrient.index')
            ->with('success', 'Data AB Mix berhasil dihapus.');
    }
}
