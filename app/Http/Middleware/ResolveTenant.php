<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Example: pull tenant/company ID from headers
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'X-Company-ID header missing'
            ], 400);
        }

        // Attach to request attributes
        $request->attributes->set('company_id', $companyId);

        return $next($request);
    }
}
