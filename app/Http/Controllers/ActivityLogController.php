<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->integer('farm_id');

        if (! $farmId) {
            return $this->errorResponse('farm_id is required.', 422);
        }

        Gate::authorize('view', Farm::findOrFail($farmId));

        $logs = ActivityLog::where('farm_id', $farmId)
            ->with(['user', 'staff'])
            ->latest('created_at')
            ->paginate(30);

        return $this->paginatedResponse($logs);
    }
}
