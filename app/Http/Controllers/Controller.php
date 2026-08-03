<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected function hasFarm(Request $request): bool
    {
        return $request->user()->farms()->exists();
    }

    protected function selectedFarm(Request $request): ?Farm
    {
        $farmId = $request->session()->get('selected_farm_id');

        if ($farmId) {
            $farm = $request->user()->farms()->find($farmId);

            if ($farm) {
                return $farm;
            }
        }

        return $request->user()->farms()->first();
    }
}
