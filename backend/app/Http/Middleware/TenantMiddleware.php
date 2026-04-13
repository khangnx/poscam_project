<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use App\Services\TenantService;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Prioritize X-Tenant-ID header
        $tenantId = $request->header('X-Tenant-ID');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
        } else {
            // Fallback to domain checking if needed
            $host = $request->getHost();
            $tenant = Tenant::where('domain', $host)->first();
        }

        if (!$tenant || $tenant->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tenant could not be identified or is inactive.',
            ], 403);
        }

        // Set the active tenant in the singleton
        app(TenantService::class)->setTenant($tenant);

        return $next($request);
    }
}
