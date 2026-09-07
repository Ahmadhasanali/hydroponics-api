<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockInDemoMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('app.demo_mode')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini dinonaktifkan dalam mode demo.',
                'errors' => (object) [],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
