<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Savings;
use App\Models\SavingsProduct;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{

    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *   path="/api/wallets/{accountNumber}/balance",
     *   tags={"Wallet"},
     *   summary="Get wallet balance",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="accountNumber", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
  
    public function getBalance($accountNumber)
    {
        $baseUrl = env('BAASCORE'). "/baas/api/v1/services/virtual-account/$accountNumber/wallet-balance";
        $response = $this->getCurl($baseUrl);
        $response = json_decode($response, true);
        if($response && $response['success'] == true){
            return $this->success($response['data'], 'Wallet balance retrieved successfully', 200);
        }else{
            return $this->error($response['message'] ?? 'Failed to retrieve wallet balance', 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function createNairaWallet(Request $request)
    {
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

        $headers = [
            'Content-Type: application/json',
            // 'x-api-key: '.env('BAASCORE_APIKEY'),
            // 'x-client-id: '.env('BAASCORE_CLIENTID'),
            // 'x-company-code: '.env('BAASCORE_COMPANYCODE'),
            'Authorization : Bearer '.env('BAASCORE_SECRET'),
        ];

        // dd($headers);

        // dd(!auth()->guard('borrower')->check());

        if(!auth()->guard('borrower')->check()){
            return $this->error('Invalid access token, Please Login', 401);
        }

        $borrower = Borrower::find(auth()->guard('borrower')->user()->id);
    
        if (!$borrower) {
            return $this->error('Customer not found!', 400);
        }

        //check if customer already has a savings account
        $wallet = Savings::where('borrower_id', $borrower->id)->where('currency','NGN')->first();
        if($wallet){
            return $this->error('Wallet already exists', 400);
        }

        $data = [
            "firstName"   => $borrower->first_name,
            "lastName"    => $borrower->last_name,
            "email"       => $borrower->email,
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

        // $response =  [ 
        //     "success" => true,
        //     "message" => "Virtual account created.",
        //     "data" => [
        //       "accountNumber" => "9999999011",
        //       "accountName" => "RexCredit/Bode Thomas",
        //       "bank" => "Rex MFBank",
        //     ],
        //     "timestamp" => "2025-08-25 02:02:47"
        // ];

        if($response && $response['success'] == true){

            //create a savings account and update account number
            Savings::updateOrCreate([
                'borrower_id' => $borrower->id,
                'currency' => 'NGN'
            ],[
                'company_id' => $borrower->company_id,
                'branch_id' => $borrower->branch_id,
                'borrower_id' => $borrower->id,
                'account_number' => $response['data']['accountNumber'],
                'account_name' => $response['data']['accountName'],
                'bank_name' => $response['data']['bank'],
                'status' => 'active',
                'available_balance' => 0,
                'ledger_balance' => 0,
                'currency' => 'NGN' ?? null,
                'savings_product_id' => 1, //default product
            ]);

            return $this->success($response['data'], 'Wallet created successfully', 200);
        }else{
            return $this->error($response['message'] ?? 'Wallet creation failed', 400);
        }   

        

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
            'Authorization: Bearer '.env('BAASCORE_SECRET')),
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
        $baseUrl = env('BAASCORE')."/baas/api/v1/services/virtual-account/create";

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
            'Authorization: Bearer '.env('BAASCORE_SECRET')),
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
