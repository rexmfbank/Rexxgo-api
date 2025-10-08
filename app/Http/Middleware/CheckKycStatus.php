<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckKycStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $borrower = auth()->guard('borrower')->user();
        if (auth()->guard('borrower')->user() && auth()->guard('borrower')->user()->kyc_status !== 'active') {
            
            return response()->json([
                'message' => 'KYC status is not active',
                'kyc_status' => $borrower->kyc_status
            ], 403); // 403 Forbidden
        }
        return $next($request);
    }
}
