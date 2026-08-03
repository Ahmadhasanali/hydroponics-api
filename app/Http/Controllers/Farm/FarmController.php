<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFarmRequest;
use App\Http\Requests\Farm\UpdateFarmRequest;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FarmController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $farms = $user->farms()->withCount('tanks')->get();
        // TODO: set selected farm in session if not set yet
        // $selectedFarm = $farms->first();
        // if ($selectedFarm) session(['selected_farm_id' => $selectedFarm->id]);

        return view('farm.index', compact('farms'));
    }

    public function create(): View
    {
        return view('farm.create');
    }

    public function store(StoreFarmRequest $request): RedirectResponse
    {
        $farm = Farm::query()->create(
            $request->validated() + ['created_by' => $request->user()->id]
        );
        $farm->users()->attach($request->user()->id, ['role' => 'owner']);
        $request->session()->put('selected_farm_id', $farm->id);

        return redirect()->route('farm.index')
            ->with('success', 'Farm berhasil ditambahkan.');
    }

    public function show(Farm $farm): View
    {
        $farm->load(['tanks', 'users']);

        return view('farm.show', compact('farm'));
    }

    public function edit(Farm $farm): View
    {
        return view('farm.edit', compact('farm'));
    }

    public function update(UpdateFarmRequest $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('update', $farm);

        $farm->update($request->validated());

        return redirect()->route('farm.index')
            ->with('success', 'Farm berhasil diperbarui.');
    }

    public function destroy(Farm $farm): RedirectResponse
    {
        Gate::authorize('delete', $farm);

        $farm->tanks()->each(fn ($tank) => $tank->delete());

        $farm->activityLogs()->delete();

        $farm->delete();

        session()->forget('selected_farm_id');

        return redirect()->route('farm.index')
            ->with('success', 'Farm berhasil dihapus.');
    }

    public function transferOwnership(Request $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('transferOwnership', $farm);

        $validated = $request->validate([
            'new_owner_id' => ['required', 'exists:users,id'],
        ]);

        $newOwner = $farm->users()->findOrFail($validated['new_owner_id']);

        if ($newOwner->id === $request->user()->id) {
            return back()->withErrors(['new_owner_id' => 'Anda tidak dapat mentransfer kepemilikan ke diri sendiri.']);
        }

        DB::transaction(function () use ($farm, $newOwner, $request) {
            $farm->users()->updateExistingPivot($newOwner->id, ['role' => 'owner']);
            $farm->users()->updateExistingPivot($request->user()->id, ['role' => 'manager']);
        });

        return redirect()->route('farm.show', $farm)
            ->with('success', 'Kepemilikan kebun berhasil ditransfer.');
    }
}
