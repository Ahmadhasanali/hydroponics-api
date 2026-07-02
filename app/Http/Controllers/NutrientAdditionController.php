<?php

namespace App\Http\Controllers;

use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NutrientAdditionController extends Controller
{
    public function index(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)->pluck('id');
        $additions = NutrientAddition::whereIn('tank_id', $tanks)
            ->with(['tank', 'user'])
            ->latest('log_date')
            ->paginate(20);

        return view('nutrient-addition.index', compact('additions'));
    }

    public function create(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('nutrient-addition.create', compact('tanks'));
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

        NutrientAddition::create($validated + ['user_id' => auth()->id()]);

        return redirect()->route('nutrient-addition.index')
            ->with('success', 'Data AB Mix berhasil disimpan.');
    }

    public function edit(Request $request, NutrientAddition $nutrientAddition): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('nutrient-addition.edit', compact('nutrientAddition', 'tanks'));
    }

    public function update(Request $request, NutrientAddition $nutrientAddition): RedirectResponse
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

        $nutrientAddition->update($validated);

        return redirect()->route('nutrient-addition.index')
            ->with('success', 'Data AB Mix berhasil diperbarui.');
    }

    public function destroy(NutrientAddition $nutrientAddition): RedirectResponse
    {
        $nutrientAddition->delete();

        return redirect()->route('nutrient-addition.index')
            ->with('success', 'Data AB Mix berhasil dihapus.');
    }
}
