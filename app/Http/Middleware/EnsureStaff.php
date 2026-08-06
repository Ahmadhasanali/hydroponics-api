<?php

namespace App\Http\Middleware;

use App\Models\Farm\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Staff) {
            abort(Response::HTTP_FORBIDDEN, 'Only staff accounts can access this resource.');
        }

        if (! $user->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'This account has been deactivated.');
        }

        return $next($request);
    }
}
