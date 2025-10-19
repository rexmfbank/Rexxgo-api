<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Savings;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\UsdWalletQueue;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                    DB::table('savings')
                        ->where('id', $savingWalletUsd->id)
                        ->update([
                            'account_number' => $bridgeResponse['source_deposit_instructions']['bank_account_number'],
                            'bank_name' => $bridgeResponse['source_deposit_instructions']['bank_name'],
                            'routing_number' => $bridgeResponse['source_deposit_instructions']['bank_routing_number'],
                            'account_name' => $bridgeResponse['source_deposit_instructions']['bank_beneficiary_name'],
                            'bridge_id' => $bridgeResponse['id'],
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
                            'bank_name' => 'ETH',
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
     *   path="/api/wallets/transfer/usd-usd",
     *   tags={"Wallet"},
     *   summary="Transfer from USD to USD",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "routing_number", "account_holder_name", "account_number", "account_type"},
     *             @OA\Property(property="amount", type="number", format="float", example=100.50),
     *             @OA\Property(property="routing_number", type="string", example="0000000"),
     *             @OA\Property(property="account_holder_name", type="string", example="Don Carlo"),
     *             @OA\Property(property="account_number", type="string", example="1234567890"),
     *             @OA\Property(property="account_type", type="string", example="checking")
     *         )
     *    ),
     *   @OA\Response(response=200, description="Created"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function usdToUsd(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'account_number' => 'required|string',
            'routing_number' => 'required|string',
            'account_holder_name' => 'required|string',
            'account_type' => 'required|string|in:checking,savings',
        ]);

        if (!auth()->guard('borrower')->check()) {
            return $this->error('Invalid access token, Please Login', 401);
        }
        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);
        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }
        $usdWallet = DB::table('savings')->where("borrower_id", $borrower->id)->where("currency", SavingsProduct::$usd)->first();
        if (!$usdWallet) {
            return $this->error('USD Wallet not found!', 400);
        }
        if ($usdWallet->available_balance < $data['amount']) {
            return $this->error('Insufficient balance!', 400);
        }
        if (!$usdWallet->bridge_id) {
            return $this->error('USD Wallet not linked!', 400);
        }
        $walletId = $usdWallet->bridge_id;

        $onBehalfOf = $borrower->bridge_customer_id;

        $result = $this->bridgeService->transferUsdToBank(
            $walletId,
            $data['amount'],
            $data,
            $onBehalfOf
        );

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['error']['message' ?? 'Transfer failed'],
            ], $result['status']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
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
