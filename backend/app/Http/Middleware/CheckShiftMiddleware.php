<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckShiftMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin and Manager can bypass shift requirements
        if ($user->hasRole(['admin', 'manager'])) {
            return $next($request);
        }

        // Staff must have an active shift
        if ($user->hasRole('staff')) {
            $activeShift = \App\Models\Shift::where('user_id', $user->id)
                ->whereNull('end_time')
                ->first();

            if (!$activeShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn cần mở ca làm việc để thực hiện hành động này.',
                    'require_shift' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
