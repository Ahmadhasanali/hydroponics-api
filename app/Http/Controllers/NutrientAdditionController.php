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
        // TODO: implement nutrient addition store with validation
        // $validated = $request->validate([...]);
        // NutrientAddition::create($validated + ['user_id' => auth()->id()]);

        return redirect()->route('nutrient-addition.index')
            ->with('success', 'Data AB Mix berhasil disimpan.');
    }

    public function edit(NutrientAddition $nutrientAddition): View
    {
        // TODO: implement nutrient addition edit

        return view('nutrient-addition.edit', compact('nutrientAddition'));
    }

    public function update(Request $request, NutrientAddition $nutrientAddition): RedirectResponse
    {
        // TODO: implement nutrient addition update with validation

        return redirect()->route('nutrient-addition.index')
            ->with('success', 'Data AB Mix berhasil diperbarui.');
    }

    public function destroy(NutrientAddition $nutrientAddition): RedirectResponse
    {
        // TODO: implement nutrient addition delete with authorization

        return redirect()->route('nutrient-addition.index')
            ->with('success', 'Data AB Mix berhasil dihapus.');
    }
}
