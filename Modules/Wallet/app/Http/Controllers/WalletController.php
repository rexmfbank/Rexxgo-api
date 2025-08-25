<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Savings;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class WalletController extends Controller
{

    use ApiResponse;
    /**
     * Display a listing of the resource.
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

}
