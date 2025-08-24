<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * Reads tenant-related headers (like company & branch IDs)
     * and attaches them as request attributes for controllers/services.
     */
    public function handle(Request $request, Closure $next)
    {
      
        // Company
        if ($request->hasHeader('X-Company-ID')) {
            $companyId = (int) $request->header('X-Company-ID');
            $request->attributes->set('company_id', $companyId);
        }

        // Branch (optional, only if you use it)
        if ($request->hasHeader('X-Branch-ID')) {
            $branchId = (int) $request->header('X-Branch-ID');
            $request->attributes->set('branch_id', $branchId);
        }

        return $next($request);
    }
}
