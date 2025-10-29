<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\Borrower;
use App\Models\LiquidationAddress;
use App\Models\Rate;
use App\Models\Savings;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\UsdWalletQueue;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Wallet\app\Http\Requests\ExternalAccountRequest;
use Modules\Wallet\app\Http\Resources\WalletResource;
use Modules\Wallet\app\Http\Resources\TransactionResource;
use Modules\Wallet\Services\BridgeService;

class WalletController extends Controller
{

    use ApiResponse;
    protected $bridgeService;

    // 2. Inject the service into the constructor
    public function __construct(BridgeService $bridgeService)
    {
        $this->bridgeService = $bridgeService;
    }

    /**
     * @OA\Get(
     *   path="/api/wallets",
     *   tags={"Wallet"},
     *   summary="Get all wallet",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function Wallets()
    {
        $wallets = DB::table('savings')->where("borrower_id", auth()->guard('borrower')->user()->id)->get();
        $wallets = WalletResource::collection($wallets);
        return $this->success($wallets, 'Wallets retrieved successfully', 200);
    }

    public function getallusdwallets()
    {
        $wallets = DB::table('savings')->where("currency", "USD")->where("bridge_id", "!=", "")->get();
        foreach ($wallets as $wallet) {
            $borrower = Borrower::where("id", $wallet->borrower_id)->first();
            if ($borrower == null) continue;
            if(empty($wallet->account_number)) continue;

            $bridgeResponse = $this->bridgeService->getVirtualAccount($borrower->bridge_customer_id, $wallet->bridge_id);

            if (empty($bridgeResponse)) {
            } else {
                $fundDestination = $bridgeResponse['destination'];
                $fundDestination['wallet_id'] = null;
                DB::table('savings')
                    ->where('id', $wallet->id)
                    ->update([
                        'account_type' => "checking",
                        'status' => "active",
                        'account_number' => $bridgeResponse['source_deposit_instructions']['bank_account_number'],
                        'bank_name' => $bridgeResponse['source_deposit_instructions']['bank_name'],
                        'routing_number' => $bridgeResponse['source_deposit_instructions']['bank_routing_number'],
                        'account_name' => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_name'],
                        'bridge_id' => $bridgeResponse['id'],
                        "bank_address" => $bridgeResponse['source_deposit_instructions']['bank_address'],
                        "bank_routing_number" => $bridgeResponse['source_deposit_instructions']['bank_routing_number'],
                        "bank_account_number" => $bridgeResponse['source_deposit_instructions']['bank_account_number'],
                        "bank_beneficiary_name" => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_name'],
                        "bank_beneficiary_address" => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_address'],
                        "payment_rail" => $bridgeResponse['source_deposit_instructions']['payment_rail'],
                        "payment_rails" => json_encode($bridgeResponse['source_deposit_instructions']['payment_rails']),
                        "destination_address" => $fundDestination['address'],
                        "destination_currency" => $fundDestination['currency'],
                        "destination_rail" => $fundDestination['payment_rail'],
                        "wallet_destination" => json_encode($fundDestination)
                    ]);
            }
        }
        $wallets = DB::table('savings')->where("currency", "USD")->where("bridge_id", "!=", "")->get();
        return $this->success($wallets, 'Wallets retrieved successfully', 200);
    }


    public function getAllBridgeWallets()
    {
        $wallets = $this->bridgeService->getAllWallets();

        foreach ($wallets['data'] as $wallet) {
            $walletId = $wallet['id'];
            $address = $wallet['address'];
            $savings = Savings::where('destination_address', $address)->first();

            if ($savings) {
                DB::table('savings')
                    ->where('id', $savings->id)
                    ->update([
                        "destination_id" => $walletId,
                    ]);
            }
        }
        return $this->success($wallets, 'Wallets retrieved successfully', 200);
    }

    /**
     * @OA\Get(
     * path="/api/wallets/transactions",
     * tags={"Wallet"},
     * summary="Get all transactions",
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="page",
     * in="query",
     * description="The page number to retrieve.",
     * required=false,
     * @OA\Schema(type="integer", default=1)
     * ),
     * @OA\Parameter(
     * name="pageSize",
     * in="query",
     * description="The number of transactions to return per page.",
     * required=false,
     * @OA\Schema(type="integer", default=15)
     * ),
     * @OA\Response(response=200, description="Success"),
     * @OA\Response(response=400, description="Bad Request"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function Transactions(Request $request)
    {
        $borrowerId = auth()->guard('borrower')->user()->id;
        $perPage = $request->query('pageSize', 15);
        $transactions = SavingsTransaction::with("savings")->where("borrower_id", $borrowerId)->paginate($perPage);
        $paginatedResource = TransactionResource::collection($transactions);

        $meta = [
            'current_page' => $transactions->currentPage(),
            'total_pages'  => $transactions->lastPage(),
            'total_items'  => $transactions->total(),
            'per_page'     => $transactions->perPage(),
        ];

        return $this->success([
            'items' => $paginatedResource,
            'meta' => $meta,
        ], 'Transactions retrieved successfully', 200);
    }

    /**
     * @OA\Get(
     *   path="/api/wallets/{accountNumber}/balance",
     *   tags={"Wallet"},
     *   summary="Get wallet balance",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="account_id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */

    public function getWalletbalance($accountNumber)
    {
        $wallet = DB::table('savings')->where("account_number", $accountNumber)->first();
        if ($wallet) {
            return $this->success(new WalletResource($wallet), 'Wallet balance retrieved successfully', 200);
        } else {
            return $this->error("Wallet not found!", 400);
        }

        // $baseUrl = env('BAASCORE') . "/baas/api/v1/services/virtual-account/$accountNumber/wallet-balance";
        // $response = $this->getCurl($baseUrl);
        // $response = json_decode($response, true);
        // if ($response && $response['success'] == true) {
        //     return $this->success($response['data'], 'Wallet balance retrieved successfully', 200);
        // } else {
        //     return $this->error($response['message'] ?? 'Failed to retrieve wallet balance', 400);
        // }
    }

    /**
     * Show the form for creating a new resource.
     */
    /**
     * @OA\Post(
     *   path="/api/wallets",
     *   tags={"Wallet"},
     *   summary="Create All wallets (NGN, USD, USDC)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Created"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createWallets(Request $request)
    {
        try {

            $errorMessage = "";

            //create ngn wallet
            $headers = [
                'Content-Type: application/json',
                'Authorization : Bearer ' . env('BAASCORE_SECRET'),
            ];

            if (!auth()->guard('borrower')->check()) {
                return $this->error('Invalid access token, Please Login', 401);
            }

            $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

            if (!$borrower) {
                return $this->error('Customer not found!', 400);
            }

            $savingsProduct = DB::table('savings_products')->where("product_name", SavingsProduct::$ngn)->first();
            $isCreateWallet = true;
            //check if customer already has a savings account
            $wallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$ngn)->first();

            // if ($wallet && $wallet->account_number != "") {
            //     $errorMessage .= "NGN Wallet already exists. ";
            // }else {
            //      $data = [
            //         "firstName"   => $borrower->first_name,
            //         "lastName"    => $borrower->last_name,
            //         "email"       => $borrower->email, //horphy2@gmail.com
            //         "phoneNumber" => $borrower->phone,
            //     ];

            //     // Merge into request
            //     $request->merge($data);

            //     // Validate request
            //     $request->validate([
            //         'firstName'   => 'required|string|max:50',
            //         'lastName'    => 'required|string|max:50',
            //         'email'       => 'required|email|max:100',
            //         'phoneNumber' => 'required|string|min:11|max:15',
            //     ]);


            //     $response = $this->curlFunction($headers, $data);
            //     $response = json_decode($response, true);
            //     if ($response && isset($response['success']) && $response['success'] == true) {
            //         $searchKeys = [
            //             'borrower_id' => $borrower->id,
            //             'currency' => 'NGN'
            //         ];

            //         $updateOrCreateData = [
            //             'company_id' => $borrower->company_id,
            //             'branch_id' => $borrower->branch_id,
            //             'borrower_id' => $borrower->id,
            //             'account_number' => $response['data']['accountNumber'],
            //             'account_name' => $response['data']['accountName'],
            //             'bank_name' => $response['data']['bank'],
            //             'status' => 'active',
            //             'available_balance' => 0,
            //             'ledger_balance' => 0,
            //             'currency' => 'NGN',
            //             'savings_product_id' => $savingsProduct ? $savingsProduct->id : 1,
            //         ];
            //         $table = DB::table('savings');
            //         $existingRecord = $table->where($searchKeys)->first();
            //         //if record already exist, update it. else create new record
            //         if ($existingRecord) {
            //             $table->where($searchKeys)->update($updateOrCreateData);
            //         } else {
            //             $insertData = array_merge($searchKeys, $updateOrCreateData);
            //             $table->insert($insertData);
            //         }

            //         // return $this->success($response['data'], 'Wallet created successfully', 200);
            //     } else {
            //         $isCreateWallet = false;
            //         $errorMessage .= $response['responseMessage'] ?? 'NGN Wallet creation failed. ';
            //     }
            // }

            //create usd wallet
            $savingsProduct = DB::table('savings_products')->where("product_name", SavingsProduct::$usd)->first();

            //check if customer already has a savings account
            //usdc wallet
            $savingWalletUsd = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usd)->first();

            if ($savingWalletUsd && $savingWalletUsd->account_number != "") {
                $errorMessage .= "USD Wallet already exists. ";
            } else {
                //lets get routing number as wallet id
                $walletId = "";
                $bridgeResponse = $this->bridgeService->createUsdcWallet($borrower->bridge_customer_id);
                if (empty($bridgeResponse)) {
                    $isCreateWallet = false;
                    $errorMessage .= "Unable to create USD wallet";
                }
                $walletId = $bridgeResponse['id'];

                $bridgeResponse = $this->bridgeService->createUsdWallet(
                    [
                        "wallet_id" => $walletId,
                        "customer_id" => $borrower->bridge_customer_id,
                        "developer_fee_percent" => $savingsProduct->developer_fee_percent
                    ]
                );
                if (empty($bridgeResponse)) {
                    $errorMessage .= "Unable to create USD wallet";
                    $isCreateWallet = false;
                } else {
                    $fundDestination = $bridgeResponse['destination'];
                    $fundDestination['wallet_id'] = $walletId;
                    DB::table('savings')
                        ->where('id', $savingWalletUsd->id)
                        ->update([
                            'account_type' => "checking",
                            'status' => "active",
                            'account_number' => $bridgeResponse['source_deposit_instructions']['bank_account_number'],
                            'bank_name' => $bridgeResponse['source_deposit_instructions']['bank_name'],
                            'routing_number' => $bridgeResponse['source_deposit_instructions']['bank_routing_number'],
                            'account_name' => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_name'],
                            'bridge_id' => $bridgeResponse['id'],
                            "bank_address" => $bridgeResponse['source_deposit_instructions']['bank_address'],
                            "bank_routing_number" => $bridgeResponse['source_deposit_instructions']['bank_routing_number'],
                            "bank_account_number" => $bridgeResponse['source_deposit_instructions']['bank_account_number'],
                            "bank_beneficiary_name" => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_name'],
                            "bank_beneficiary_address" => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_address'],
                            "payment_rail" => $bridgeResponse['source_deposit_instructions']['payment_rail'],
                            "payment_rails" => json_encode($bridgeResponse['source_deposit_instructions']['payment_rails']),
                            "destination_id" => $walletId,
                            "destination_address" => $fundDestination['address'],
                            "destination_currency" => $fundDestination['currency'],
                            "destination_rail" => $fundDestination['payment_rail'],
                            "wallet_destination" => json_encode($fundDestination)
                        ]);
                }
            }


            //create usdc wallet
            $savingWallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usdc)->first();

            if ($savingWallet && $savingWallet->account_number != "") {
                $errorMessage .= 'USDC Wallet already exists';
            } else {
                $bridgeResponse = $this->bridgeService->createUsdcWallet($borrower->bridge_customer_id);
                if (empty($bridgeResponse)) {
                    $errorMessage .= "Unable to create USDC wallet";
                    $isCreateWallet = false;
                } else {
                    DB::table('savings')
                        ->where('id', $savingWallet->id)
                        ->update([
                            'account_number' => $bridgeResponse['address'],
                            'bank_name' => $bridgeResponse['chain'] ?? 'ethereum',
                            'routing_number' => $bridgeResponse['id'], //wallet id
                            'bridge_id' => $bridgeResponse['id'], //wallet id
                            'account_name' => $borrower->full_name,
                            "status" => "active"
                        ]);
                }
            }
            if ($isCreateWallet) {
                DB::table('borrowers')->where('id', $borrower->id)->update(['wallet_created' => true]);
            }
            $status = $isCreateWallet ? 200 : 400;
            return response()->json([
                'message' => $status == 200 ? 'Wallets Created.' : $errorMessage,
                'errorMessage' => $errorMessage
            ], $status);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => $th->getMessage(),
                'status' => false
            ], 500);
        }
    }


    /**
     * Show the form for creating a new resource.
     */
    /**
     * @OA\Post(
     *   path="/api/wallets/ngn",
     *   tags={"Wallet"},
     *   summary="Create NGN wallet",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Created"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createNairaWallet(Request $request)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization : Bearer ' . env('BAASCORE_SECRET'),
        ];

        if (!auth()->guard('borrower')->check()) {
            return $this->error('Invalid access token, Please Login', 401);
        }

        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

        $savingsProduct = DB::table('savings_products')->where("product_name", SavingsProduct::$ngn)->first();

        //check if customer already has a savings account
        $wallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$ngn)->first();

        if ($wallet && $wallet->account_number != "") {
            return $this->error('Wallet already exists', 400);
        }

        $data = [
            "firstName"   => $borrower->first_name,
            "lastName"    => $borrower->last_name,
            "email"       => $borrower->email, //horphy2@gmail.com
            "phoneNumber" => $borrower->phone,
        ];

        // Merge into request
        $request->merge($data);

        // Validate request
        $request->validate([
            'firstName'   => 'required|string|max:50',
            'lastName'    => 'required|string|max:50',
            'email'       => 'required|email|max:100',
            'phoneNumber' => 'required|string|min:11|max:15',
        ]);


        $response = $this->curlFunction($headers, $data);
        $response = json_decode($response, true);
        if ($response && isset($response['success']) && $response['success'] == true) {
            $searchKeys = [
                'borrower_id' => $borrower->id,
                'currency' => 'NGN'
            ];

            $updateOrCreateData = [
                'company_id' => $borrower->company_id,
                'branch_id' => $borrower->branch_id,
                'borrower_id' => $borrower->id,
                'account_number' => $response['data']['accountNumber'],
                'account_name' => $response['data']['accountName'],
                'bank_name' => $response['data']['bank'],
                'status' => 'active',
                'available_balance' => 0,
                'ledger_balance' => 0,
                'currency' => 'NGN',
                'savings_product_id' => $savingsProduct ? $savingsProduct->id : 1,
            ];
            $table = DB::table('savings');
            $existingRecord = $table->where($searchKeys)->first();
            //if record already exist, update it. else create new record
            if ($existingRecord) {
                $table->where($searchKeys)->update($updateOrCreateData);
            } else {
                $insertData = array_merge($searchKeys, $updateOrCreateData);
                $table->insert($insertData);
            }

            return $this->success($response['data'], 'Wallet created successfully', 200);
        } else {
            return $this->error($response['responseMessage'] ?? 'Wallet creation failed', 400);
        }
    }


    /**
     * @OA\Post(
     *   path="/api/wallets/usd",
     *   tags={"Wallet"},
     *   summary="Create USD wallet",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Created"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createUsWallet(Request $request)
    {
        if (!auth()->guard('borrower')->check()) {
            return $this->error('Invalid access token, Please Login', 401);
        }

        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

        $savingsProduct = DB::table('savings_products')->where("product_name", SavingsProduct::$usd)->first();

        //check if customer already has a savings account
        //usdc wallet
        $savingWalletUsd = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usd)->first();

        if ($savingWalletUsd && $savingWalletUsd->account_number != "") {
            return $this->error('Wallet already exists', 400);
        }

        try {
            //lets get routing number as wallet id
            $walletId = "";
            $bridgeResponse = $this->bridgeService->createUsdcWallet($borrower->bridge_customer_id);
            if (empty($bridgeResponse)) {
                return $this->error("Unable to create USD wallet", 400);
            }
            $walletId = $bridgeResponse['id'];

            $bridgeResponse = $this->bridgeService->createUsdWallet(
                [
                    "wallet_id" => $walletId,
                    "customer_id" => $borrower->bridge_customer_id,
                    "developer_fee_percent" => $savingsProduct->developer_fee_percent
                ]
            );
            if (empty($bridgeResponse)) {
                return $this->error("Unable to create USD wallet", 400);
            }

            DB::table('savings')
                ->where('id', $savingWalletUsd->id)
                ->update([
                    'account_number' => $bridgeResponse['source_deposit_instructions']['bank_account_number'],
                    'bank_name' => $bridgeResponse['source_deposit_instructions']['bank_name'],
                    'routing_number' => $bridgeResponse['source_deposit_instructions']['bank_routing_number'],
                    'account_name' => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_name'],
                    'bridge_id' => $bridgeResponse['id'],
                ]);

            return response()->json([
                'message' => 'USD wallet Created.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *   path="/api/wallets/usdc",
     *   tags={"Wallet"},
     *   summary="Create USDC wallet",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Created"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createUsdcWallet(Request $request)
    {
        if (!auth()->guard('borrower')->check()) {
            return $this->error('Invalid access token, Please Login', 401);
        }

        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

        //check if customer already has a savings account
        //usdc wallet
        $savingWallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usdc)->first();

        if ($savingWallet && $savingWallet->account_number != "") {
            return $this->error('Wallet already exists', 400);
        }

        try {
            $bridgeResponse = $this->bridgeService->createUsdcWallet($borrower->bridge_customer_id);
            if (empty($bridgeResponse)) {
                return $this->error("Unable to create USD wallet", 400);
            }
            DB::table('savings')
                ->where('id', $savingWallet->id)
                ->update([
                    'account_number' => $bridgeResponse['address'],
                    'bank_name' => 'ETH',
                    'routing_number' => $bridgeResponse['id'], //wallet id
                    'bridge_id' => $bridgeResponse['id'], //wallet id
                    'account_name' => $borrower->full_name,
                    "status" => "active"
                ]);
            return response()->json([
                'message' => 'USD wallet Created.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *   path="/api/wallets/usdc/address",
     *   tags={"Wallet"},
     *   summary="Get USDC Liquidation Address",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Created"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getLiquidationAddress(Request $request)
    {
        if (!auth()->guard('borrower')->check()) {
            return $this->error('Invalid access token, Please Login', 401);
        }

        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

        //check if customer already has a savings account
        //usdc wallet
        $savingWallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usdc)->first();

        if (!$savingWallet || $savingWallet->account_number == "") {
            return $this->error('Invalid wallet', 400);
        }
        $address = LiquidationAddress::where("savings_id", $savingWallet->id)->first();
        if($address != null){
            return $this->success($address);
        }

        try {
            $bridgeResponse = $this->bridgeService->createLiquidationAddress(
                $borrower->bridge_customer_id, [
                    "currency" => "USDC",
                    "chain" => "ethereum",
                    "bridge_wallet_id" => $savingWallet->bridge_id,
                ]);
            if (empty($bridgeResponse)) {
                return $this->error("Unable to create Liquidation address", 400);
            }

            LiquidationAddress::create([
                "bridge_liquidation_id"      => $bridgeResponse['id'], 
                "chain"                      => $bridgeResponse['chain'],
                "savings_id"                 => $savingWallet->id, 
                "address"                    => $bridgeResponse['address'],
                "currency"                   => $bridgeResponse['currency'],
                "customer_id"                => $bridgeResponse['customer_id'],
                "destination_payment_rail"   => $bridgeResponse['destination_payment_rail'],
                "destination_currency"       => $bridgeResponse['destination_currency'],
                "destination_address"        => $bridgeResponse['destination_address'],
                "state"                      => $bridgeResponse['state'] ?? 'active', 
            ]);

        $address = LiquidationAddress::where("savings_id", $savingWallet->id)->first();
            
            return $this->success($address);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
 * @OA\Get(
 *     path="/api/wallets/beneficiaries",
 *     summary="Get beneficiaries by currency",
 *     description="Returns list of saved external beneficiaries for authenticated user filtered by currency.",
 *     tags={"Wallet"},
*    security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="currency",
 *         in="query",
 *         required=true,
 *         description="Currency code (usd, ngn, etc.)",
 *         @OA\Schema(type="string", example="usd")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Successful Retrieval",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Beneficiaries retrieved successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="account_owner_name", type="string", example="John Doe"),
 *                     @OA\Property(property="bank_name", type="string", example="Wells Fargo"),
 *                     @OA\Property(property="currency", type="string", example="usd"),
 *                     @OA\Property(property="external_account_id", type="string", example="ext_81hds7821")
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=400,
 *         description="Validation Error"
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 */
public function getBeneficiariesByCurrency(Request $request)
{
    $request->validate([
        'currency' => 'required|string'
    ]);

    $currency = strtolower($request->currency);
    $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

    $beneficiaries = Beneficiary::where('borrower_id', $borrower->id)
        ->where('currency', $currency)
        ->get([
            'id',
            'account_owner_name',
            'bank_name',
            'currency',
            'external_account_id',
        ]);

    return response()->json([
        'status' => true,
        'message' => 'Beneficiaries retrieved successfully',
        'data' => $beneficiaries
    ]);
}



    //write a GET curl function to get wallet balance
    public function getCurl($baseUrl)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('BAASCORE_SECRET')
            ),
        ));

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            return $this->error('Curl error: ' . $error_msg, 500);
        }
        curl_close($curl);

        return  $response;
    }

    //write a CURL function reusable 
    public function curlFunction($headers, $data)
    {

        $curl = curl_init();
        $baseUrl = env('BAASCORE') . "/baas/api/v1/services/virtual-account/create";

        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('BAASCORE_SECRET')
            ),
        ));

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            return $this->error('Curl error: ' . $error_msg, 500);
        }
        curl_close($curl);

        return  $response;
    }

    /**
     * Create savings accounts for a borrower (NGN, USD, USDC)
     */
    public function createUserWallets(int $borrowerId): array
    {
        try {
            $borrower = Borrower::find($borrowerId);

            if (!$borrower) {
                throw new \Exception("Borrower not found for ID: {$borrowerId}");
            }

            $products = SavingsProduct::whereIn('product_name', ['NGN', 'USD', 'USDC'])->get();

            if ($products->count() !== 3) {
                throw new \Exception("Expected 3 savings products (NGN, USD, USDC) but found {$products->count()}");
            }

            $createdAccounts = [];

            // Create savings accounts for each product
            foreach ($products as $product) {
                $account = $this->createSavingsAccount($borrower, $product);
                if ($account) {
                    $createdAccounts[] = $account;
                }
            }

            Log::info("Successfully created " . count($createdAccounts) . " savings accounts for borrower ID: {$borrowerId}");

            return [
                'success' => true,
                'message' => 'Savings accounts created successfully',
                'accounts' => $createdAccounts
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create savings accounts for borrower ID: {$borrowerId}. Error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'accounts' => []
            ];
        }
    }

    /**
     * @OA\Post(
     *     path="/api/wallets/transfer",
     *     tags={"Wallet"},
     *     summary="Transfer funds between wallets",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"source_wallet_id", "destination_wallet_id", "amount"},
     *             @OA\Property(property="source_wallet_id", type="integer", example=1),
     *             @OA\Property(property="destination_wallet_id", type="integer", example=2),
     *             @OA\Property(property="amount", type="number", example=50.00),
     *             @OA\Property(property="transaction_pin", type="string", example=1234)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Transfer initiated successfully"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     * )
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'source_wallet_id' => 'required|integer',
            'destination_wallet_id' => 'required|integer|different:source_wallet_id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_pin' => 'required',
        ]);
        try {


            $userId = auth()->guard('borrower')->id();
            $borrower = Borrower::find(auth()->guard('borrower')->user()->id);
            if (!$borrower) {
                return $this->error('Customer not found!', 400);
            }

            // if (!Hash::check($request->transaction_pin, $borrower->pin)) {
            //     return $this->error('Invalid Pin', 400);
            // }

            if ($borrower->bridge_customer_id == "") {
                return $this->error('Kyc not completed!', 400);
            }
            $sourceWallet = Savings::where('id', $request->source_wallet_id)
                ->where('borrower_id', $userId)
                ->first();
            if ($sourceWallet == null) {
                return $this->error('Source wallet not found!', 400);
            }
            $destinationWallet = Savings::where('id', $request->destination_wallet_id)
                ->where('borrower_id', $userId)
                ->first();

            if ($destinationWallet == null) {
                return $this->error('Destination wallet not found!', 400);
            }

            if ($sourceWallet->bridge_id == "" || $destinationWallet->bridge_id == "") {
                return $this->error('One of the wallets is not linked!', 400);
            }
            $amount = (float) $request->amount;

            if ($sourceWallet->available_balance < $amount) {
                return $this->error('Insufficient balance.', 400);
            }
            $data = null;
            $reference = "REX-" . $sourceWallet->currency . "-" . date("Ymdhsi") . '-' . $borrower->id . uniqid();

            if ($sourceWallet->currency == SavingsProduct::$usdc && $destinationWallet->currency == SavingsProduct::$usd) {

                $destination = [
                    'payment_rail' => $destinationWallet['destination_rail'] ?? "ethereum",
                    'bridge_wallet_id' => $destinationWallet['destination_id'],
                    'currency' => $destinationWallet['destination_currency'],
                ];

                $source = [
                    'payment_rail' => 'bridge_wallet',
                    'bridge_wallet_id' => $sourceWallet->bridge_id,
                    'currency' => 'usdc',
                ];

                $data = $this->bridgeService->Transfer($borrower->bridge_customer_id, $reference, $source, $destination, $amount);
            } elseif ($sourceWallet->currency == SavingsProduct::$usd && $destinationWallet->currency == SavingsProduct::$usdc) {

                $destination = [
                    'payment_rail' => $destinationWallet['payment_rail'] != "" ? $destinationWallet['payment_rail'] : 'ethereum', //not suppose to be hardcoded
                    'bridge_wallet_id' => $destinationWallet['bridge_id'],
                    'currency' => "usdc",
                ];

                $source = [
                    'payment_rail' => 'bridge_wallet',
                    'bridge_wallet_id' => $sourceWallet->destination_id, //bridge aumatically route usd transaction to a crypto wallet called desitination, a Bridge Wallet
                    'currency' => 'usdc',
                ];
                $data = $this->bridgeService->Transfer($borrower->bridge_customer_id, $reference, $source, $destination, $amount);
            } else {
                return $this->error("Unsupported conversion");
            }
           
            if(!isset($data['id'])){
                return $this->error($data['message'] ?? "Unsupported conversion");
            }

            $transferId = $data['id'];

            $sourceWallet->available_balance -= $amount;
            $sourceWallet->ledger_balance -= $amount;
            
            $sourceWallet->save();

            $newTransaction = SavingsTransaction::create([
                'reference' => $reference,
                'borrower_id' => $userId,
                'savings_id' => $sourceWallet->id,
                'transaction_amount' => $amount,
                'balance' => $sourceWallet->available_balance,
                'transaction_date' => now()->toDateString(),
                'transaction_time' => now()->toTimeString(),
                'transaction_type' => 'debit',
                'transaction_description' => "Wallet transfer to {$destinationWallet->name}",
                'debit' => $amount,
                'credit' => 0,
                'status_id' => 'pending',
                'currency' => $sourceWallet->currency ?? 'USD',
                'external_response' => json_encode($data, JSON_PRETTY_PRINT),
                'external_tx_id' => $transferId . '_init',
                'provider' => 'bridge',
            ]);

            $res = new TransactionResource($newTransaction);

            return $this->success($res, 'Transfer initiated successfully.');
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/wallets/transfer/usd",
     *     summary="Transfer funds to a US external bank account",
     *     tags={"Wallet"},
     *    security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="currency", type="string", example="usd"),
     *             @OA\Property(property="bank_name", type="string", example="Wells Fargo"),
     *             @OA\Property(property="account_owner_name", type="string", example="John Doe"),
     *             @OA\Property(property="account_type", type="string", example="us"),
     *
     *             @OA\Property(
     *                 property="account",
     *                 type="object",
     *                 @OA\Property(property="account_number", type="string", example="1210002481115"),
     *                 @OA\Property(property="routing_number", type="string", example="121000248"),
     *                 @OA\Property(property="checking_or_savings", type="string", example="checking")
     *             ),
     *
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 @OA\Property(property="street_line_1", type="string", example="123 Main St"),
     *                 @OA\Property(property="city", type="string", example="San Francisco"),
     *                 @OA\Property(property="state", type="string", example="CA"),
     *                 @OA\Property(property="postal_code", type="string", example="94102"),
     *                 @OA\Property(property="country", type="string", example="USA")
     *             ),
     *
     *             @OA\Property(property="narration", type="string", example="Withdrawal to US Bank"),
     *             @OA\Property(property="transaction_pin", type="string", example="1234"),
     *             @OA\Property(property="amount", type="number", example=100.50)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Transfer successful"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation or transfer error"
     *     )
     * )
     */

    public function transfertoUsBank(ExternalAccountRequest $request)
    {
        $data = $request->validated();

        if (!auth()->guard('borrower')->check()) {
            return $this->error('Invalid access token, Please Login', 401);
        }

        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

        $wallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usd)->first();

        if (!$wallet || $wallet->bridge_id == "" || $wallet->account_number == "") {
            return $this->error('Invalid USD wallet', 400);
        }

        if(!Hash::check($data['transaction_pin'], $borrower->pin)) {
                return $this->error('Invalid transaction pin', 400);
            }

        if ($wallet->available_balance < $data['amount']) {
            return $this->error("Insufficient balance", 400);
        }

        $accountNumber = $data['account']['account_number'];
        $routingNumber = $data['account']['routing_number'];

        $beneficiary = Beneficiary::where([
            'borrower_id' => $borrower->id,
            'account_number_last4' => $accountNumber,
            'routing_number' => $routingNumber
        ])->first();

        if (!$beneficiary) {
            $externalAccountResponse = $this->bridgeService
                ->createExternalAccount($borrower->bridge_customer_id, $data);

            if (isset($externalAccountResponse['error'])) {
                return $this->error($externalAccountResponse['error'], 400);
            }

            $beneficiary = Beneficiary::create([
                'borrower_id' => $borrower->id,
                'external_account_id' => $externalAccountResponse['id'],
                'account_number_last4' => $accountNumber,
                'routing_number' => $routingNumber,
                'bank_name' => $data['bank_name'],
                'account_owner_name' => $data['account_owner_name'],
                'currency' => $data['currency'],
            ]);
        }
        $reference = "REX-" . $wallet->currency . "-" . date("Ymdhsi") . '-' . $borrower->id . uniqid();

        $destination = [
            'payment_rail' => 'ach',
            'external_account_id' => $beneficiary->external_account_id,
            'currency' => "usd",
            'ach_reference' => "Withdrawal",
        ];

        $source = [
            'payment_rail' => 'bridge_wallet',
            'bridge_wallet_id' => $wallet->destination_id,
            'currency' => 'usdc',
        ];

        try {
            $transferResponse = $this->bridgeService->Transfer($borrower->bridge_customer_id, $reference, $source, $destination, $data['amount']);


            if (isset($transferResponse['error'])) {
                return $this->error($transferResponse['error'], 400);
            }

            // /** ✅ 3. Deduct wallet */
            $wallet->decrementBalance($data['amount']);

            return response()->json([
                "message" => "Transfer successful",
                "external_account" => $externalAccountResponse,
                // "transfer" => $transferResponse
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
 * @OA\Post(
 *     path="/api/wallets/transfer/usdc",
 *     summary="Transfer USDC from Bridge Wallet to external crypto wallet",
 *     tags={"Wallet"},
 *    security={{"bearerAuth":{}}},
 * 
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"amount","destination_address","network","transaction_pin"},
 *             @OA\Property(property="amount", type="string", example="25.00"),
 *             @OA\Property(property="transaction_pin", type="string", example="1234"),
 *             @OA\Property(property="destination_address", type="string", example="0x1234567890abcdef"),
 *             @OA\Property(property="network", type="string", example="ethereum"),
 *             @OA\Property(property="memo", type="string", example="", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Transfer successful"
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error processing request"
 *     )
 * )
 */
public function transferCrypto(Request $request)
{
    $data = $request->validate([
        'amount' => 'required|string',
        'destination_address' => 'required|string',
        'transaction_pin' => 'required|string',
        'network' => 'required|string|in:ethereum,polygon,arbitrum,optimism,avalanche_c_chain,solana',
        'memo' => 'nullable|string'
    ]);

    if (!auth()->guard('borrower')->check()) {
        return $this->error('Invalid access token, Please Login', 401);
    }

    $borrower = Borrower::find(auth()->guard('borrower')->user()->id);

    if (!$borrower) {
        return $this->error('Customer not found!', 400);
    }

    $wallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usdc)->first();

    if (!$wallet || $wallet->bridge_id == "") {
        return $this->error('Invalid USD wallet', 400);
    }

    if(!Hash::check($data['transaction_pin'], $borrower->pin)) {
            return $this->error('Invalid transaction pin', 400);
        }

    if ($wallet->available_balance < $data['amount']) {
        return $this->error("Insufficient balance", 400);
    }
    
    $destination = [
        'payment_rail' => $data['network'],
        'to_address' => $data['destination_address'],
        'currency' => 'usdc',
    ];

    $source = [
        'payment_rail' => 'bridge_wallet',
        'bridge_wallet_id' => $wallet->bridge_id,
        'currency' => 'usdc',
    ];

    try {
        
            $reference = "REX-" . $wallet->currency . "-" . date("Ymdhsi") . '-' . $borrower->id . uniqid();
    
    $data = $this->bridgeService->Transfer($borrower->bridge_customer_id, $reference, $source, $destination, $data['amount']);
    return response()->json([
                "message" => "Transfer successful",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
}


    /**
     * @OA\Get(
     *     path="/api/wallets/rates",
     *     summary="Get currency exchange rates",
     *     description="Fetch exchange rates for a given base currency from the rates table.",
     *     tags={"Wallet"},
     *
     *     @OA\Parameter(
     *         name="base",
     *         in="query",
     *         description="Base currency code (e.g., USD, USDC, NGN). Defaults to USD.",
     *         required=false,
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with rates",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="base", type="string", example="USD"),
     *             @OA\Property(
     *                 property="rates",
     *                 type="object",
     *                 additionalProperties=@OA\Property(type="number", example=1.25)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No rates found for the given base currency",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No rates for USD")
     *         )
     *     )
     * )
     */
    public function Rates(Request $request)
    {
        $base = strtoupper($request->query('base', 'USD'));
        $rates = Rate::where('base_currency', $base)->get();

        if ($rates->isEmpty()) {
            return $this->error("No rates for {$base}", 404);
        }
        return $this->success([
            'base' => $base,
            'rates' => $rates->pluck('rate', 'target_currency'),
        ], "Rates");
    }

    /**
     * Create a savings account for a specific product
     */
    private function createSavingsAccount(Borrower $borrower, SavingsProduct $product): ?Savings
    {
        // Check if savings account already exists for this product
        $existingSavings = Savings::where('borrower_id', $borrower->id)
            ->where('savings_product_id', $product->id)
            ->first();

        if ($existingSavings) {
            Log::info("Savings account already exists for borrower ID: {$borrower->id}, product: {$product->product_name}");
            return $existingSavings;
        }
        // Generate account number
        $accountNumber = null;

        $savings = Savings::create([
            'borrower_id' => $borrower->id,
            'savings_product_id' => $product->id,
            'account_number' => $accountNumber,
            'account_name' => "{$borrower->first_name} {$borrower->last_name}",
            'currency' => $product->product_name,
            'available_balance' => 0,
            'ledger_balance' => 0,
            'minimum_balance_for_interest' => $product->minimum_balance_for_interest_rate,
            'interest_rate_per_annum' => $product->interest_rate_per_annum,
            'minimum_balance_for_withdrawal' => $product->minimum_balance_for_withdrawal,
            'interest_posting_frequency' => $product->interest_posting_frequency,
            'savings_description' => "{$product->product_name} Savings Account for {$borrower->first_name} {$borrower->last_name}",
            'account_tier' => 'standard',
            'account_type' => 'savings',
            "status" => "inactive"
        ]);

        return $savings;
    }
}
