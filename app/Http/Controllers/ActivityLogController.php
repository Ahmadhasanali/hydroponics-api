<?php

namespace App\Http\Controllers;

use App\Models\Farm\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $logs = ActivityLog::where('farm_id', $farmId)
            ->with('user')
            ->latest('created_at')
            ->paginate(30);

        return view('activity-logs.index', compact('logs'));
    }
}
