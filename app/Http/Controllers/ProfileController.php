<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $farms = $user->farms()->withCount('tanks')->get();

        return view('profile.index', compact('user', 'farms'));
    }
}
