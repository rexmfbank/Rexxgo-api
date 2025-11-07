<?php

namespace Modules\Treasury\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Treasury;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class TreasuryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *   path="/api/treasury",
     *   tags={"Treasury"},
     *   summary="Get all treasury",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function Treasury()
    {
        $treasury = Treasury::with('wallet')->get();
        return $this->success($treasury, 'Treasury retrieved successfully', 200);
    }
}
