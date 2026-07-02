<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFarmUserRequest;
use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FarmUserController extends Controller
{
    public function index(Request $request, Farm $farm): View
    {
        $farm->load(['users' => function ($query) {
            $query->orderBy('pivot_created_at');
        }]);

        return view('farm-members.index', compact('farm'));
    }

    public function create(Request $request, Farm $farm): View
    {
        $this->authorize('update', $farm);

        return view('farm-members.create', compact('farm'));
    }

    public function store(StoreFarmUserRequest $request, Farm $farm): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('name', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => 'User dengan email tersebut tidak ditemukan.'])
                ->withInput();
        }

        if ($farm->users()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'User tersebut sudah menjadi anggota farm.'])
                ->withInput();
        }

        $farm->users()->attach($user->id, ['role' => 'member']);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function destroy(Request $request, Farm $farm, FarmUser $farmUser): RedirectResponse
    {
        $this->authorize('update', $farm);

        if ($farmUser->user_id === $request->user()->id) {
            return back()->withErrors(['error' => 'Anda tidak dapat menghapus diri sendiri.']);
        }

        $farmUser->delete();

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Anggota berhasil dihapus.');
    }
}
