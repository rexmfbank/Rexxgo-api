<?php

namespace Modules\Profile\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Profile\app\Http\Resources\UserResource;
use Modules\Wallet\Services\BridgeService; 


class ProfileController extends Controller
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
     *   path="/api/profile",
     *   tags={"Profile"},
     *   summary="Get user profile",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        if (!auth()->guard('borrower')->check()) {
            return $this->error('Unauthorized', 401);
        }

        $borrower = auth()->guard('borrower')->user();

        return $this->success(new UserResource($borrower), 'Profile retrieved successfully');
    }

    /**
     * @OA\Post(
     *   path="/api/profile/update",
     *   tags={"Profile"},
     *   summary="Update user profile",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="bvn", type="string", example="12345678901", description="For Nigeria users only"),
     *       @OA\Property(property="nin", type="string", example="12345678901", description="For Nigeria users only")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Profile updated successfully"),
     *   @OA\Response(response=400, description="Validation error"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'bvn' => 'required_without:nin|string|size:11|regex:/^\d{11}$/',
            'nin' => 'required_without:bvn|string|size:11|regex:/^\d{11}$/',
        ]);

        $borrower = auth()->guard('borrower')->user();
        // Validate based on country
        $country = $borrower->country;
        if ($borrower->country === 'USA') {
            return $this->error('Something went wrong', 500);
        } 


        try {
            if (isset($validatedData['bvn'])) {
                if($borrower->bvn != ""){
                    return $this->error('BVN already exist!', 500);
                }
                $accessToken = $this->getQoreIdToken();
                if($accessToken == null) {
                    return $this->error('Unable to verify your identity. API error.', 500);
                }
                $verifyBvnBasic = $this->verifyBvnBasic($validatedData['bvn'], $borrower->first_name, $borrower->last_name, $accessToken);
                
                if($verifyBvnBasic == null){
                    return $this->error('Invalid BVN supplied');
                }
                if($verifyBvnBasic['status']['status'] == "verified"){
                    $bvn = $verifyBvnBasic['bvn'];
                    $inputFormat = 'd-m-Y';
                    $outputFormat = 'Y-m-d';

                    $dateObject = \DateTime::createFromFormat($inputFormat, $bvn['birthdate']);
                    $formattedDate = $dateObject->format($outputFormat);
                        
                    $updateData = [
                        "first_name" => $bvn['firstname'],
                        "last_name"=> $bvn['lastname'],
                        "middle_name"=> $bvn['middlename'],
                        "dob"=> $formattedDate,
                        "gender"=> $bvn['gender'],
                        "id_type" => "bvn",
                        "id_value" => $validatedData['bvn'], 
                        "bvn" => $validatedData['bvn'], 
                        "kyc_status" => "active", 
                    ];
                    DB::table('borrowers')->where('id', $borrower->id)->update($updateData);
                    return $this->success(new UserResource($borrower), 'Profile update successfully');
                }
                
                
            } elseif (isset($validatedData['nin'])) {
                $updateData['id_type'] = 'NIN';
                $updateData['id_value'] = $validatedData['nin'];
                $borrower = Borrower::find($borrower->id);

                return $this->success(new UserResource($borrower), 'Profile updated successfully');
            }else {
                return $this->error('Unable to verify your identity', 500);
            }

        } catch (\Exception $e) {
            
            return $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }


    /**
     * @OA\Post(
     *   path="/api/profile/kyc",
     *   tags={"Profile"},
     *   summary="Start KYC process",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Profile updated successfully"),
     *   @OA\Response(response=400, description="Validation error"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function startKyc(Request $request)
    {
        // $validatedData = $request->validate([
        //     'dob' => 'required|date|before:today',
        //     'address' => 'required|string|max:255',
        //     'city' => 'required|string|max:100',
        //     'state' => 'required|string|max:50',
        //     'zipcode' => 'required|string|max:10',
        //     'ssn' => 'required|string|regex:/^\d{3}-\d{2}-\d{4}$/',
        // ]);

        $borrower = auth()->guard('borrower')->user();
        // Validate based on country
        try {
            // $updateData['id_type'] = 'SSN';
            // $updateData['id_value'] = $validatedData['ssn'];
            // $updateData['dob'] = $validatedData['dob'];
            // $updateData['address'] = $validatedData['address'];
            // $updateData['city'] = $validatedData['city'];
            // $updateData['state'] = $validatedData['state'];
            // $updateData['zipcode'] = $validatedData['zipcode'];
            // DB::table('borrowers')->where('id', $borrower->id)->update($updateData);
            $borrower = Borrower::find($borrower->id);
            
            if($borrower->kyc_status != "active"){

                if($borrower->bridge_customer_id !== "" && $borrower->kyc_link != ""){
                    return $this->success([
                        "kyc_link" => $borrower->kyc_link,
                        "kyc_status" => $borrower->kyc_status,
                        "tos_link" => $borrower->tos_link,
                        'tos_status' => $borrower->tos_status,
                        'rejection_reasons' => empty($borrower->rejection_reasons) ? [] : json_decode($borrower->rejection_reasons),
                    ], 'Profile updated successfully');
                }
                $kycData = [
                    'full_name' => $borrower->first_name . ' ' . $borrower->last_name,
                    'email' => $borrower->email,
                    'type' => 'individual',
                ];
        
                $response = $this->bridgeService->createKycLink($kycData);

                if (!$response) {
                return $this->error('KYC service is currently unavailable', 500);
                }

                $customerId = $response['customer_id'];
                $kyc_link = $response['kyc_link'];
                $tos_link = $response['tos_link'];
                $kyc_status = $response['kyc_status'];
                $rejection_reasons = $response['rejection_reasons'];
                $tos_status = $response['tos_status'];

                $updateData['bridge_customer_id'] = $customerId;
                $updateData['kyc_link'] = $kyc_link;
                $updateData['tos_link'] = $tos_link;
                $updateData['tos_status'] = $tos_status;
                $updateData['rejection_reasons'] = json_encode($rejection_reasons, JSON_PRETTY_PRINT);
                $updateData['kyc_status'] = $kyc_status == "not_started" ? "pending" : ($kyc_status == "approved" ? "active" : "pending");

                DB::table('borrowers')->where('id', $borrower->id)->update($updateData);
                $borrower = Borrower::find($borrower->id);

                return $this->success([
                    "kyc_link" => $kyc_link,
                    "kyc_status" => $borrower->kyc_status,
                    'tos_link' => $tos_link,
                    'tos_status' => $borrower->tos_status,
                    'rejection_reasons' => empty($borrower->rejection_reasons) ? [] : json_decode($borrower->rejection_reasons),
                ], 'Profile updated successfully');
            }

            return $this->success([
                        "kyc_link" => $borrower->kyc_link,
                        "kyc_status" => $borrower->kyc_status,
                        "tos_link" => $borrower->tos_link,
                        'tos_status' => $borrower->tos_status,
                        'rejection_reasons' => empty($borrower->rejection_reasons) ? [] : json_decode($borrower->rejection_reasons),
                    ], 'Profile updated successfully');

        } catch (\Exception $e) {
            
            return $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }


/**
     * Sends a POST request to QoreID to verify BVN and name.
     *
     * @param string $bvn The BVN.
     * @param string $firstName The first name.
     * @param string $lastName The last name.
     * @param string $accessToken The Bearer token.
     * @return object|null The decoded JSON response object on success, or null on failure/error.
     */
    private function verifyBvnBasic(
        string $bvn,
        string $firstName,
        string $lastName,
        string $accessToken
    ): ?array {
        $bvnPath = trim(rawurlencode($bvn));
        $url = env('QOREID_BASEURL') . "/v1/ng/identities/bvn-basic/{$bvnPath}";

        $payload = [
            'firstname' => $firstName,
            'lastname' => $lastName
        ];

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['accept' => 'application/json'])
                ->asJson() 
                ->post($url, $payload);
            
            if ($response->successful()) {
                return $response->json(); 
            }
            return null;

        } catch (\Exception $e) {

            return null;
        }
    }

    /**
     * Attempts to retrieve the access token from the QoreID API.
     *
     * @return string|null The access token string or null on failure.
     */
    private function getQoreIdToken(): ?string
    {
        $clientId = env('QOREID_CLIENT_ID');
        $secret = env('QOREID_SECRET');
        $tokenUrl = env('QOREID_BASEURL').'/token';

        if (!$clientId || !$secret || !$tokenUrl) {
            return null; // Configuration missing
        }

        $payload = ['clientId' => $clientId, 'secret' => $secret];

        try {
            $response = Http::withHeaders(['accept' => 'text/plain'])
                ->post($tokenUrl, $payload);
            info($response);
            if ($response->successful()) {
                $tokenData = $response->json(); 
                return $tokenData['accessToken'] ?? null;
            }
            return null;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Get(
     *   path="/api/profile/kyc-status",
     *   tags={"Profile"},
     *   summary="Get KYC status",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getKycStatus()
    {
        if (!auth()->guard('borrower')->check()) {
            return $this->error('Unauthorized', 401);
        }

        $borrower = auth()->guard('borrower')->user();
        
        return $this->success([
            'kyc_status' => $borrower->kyc_status,
            'id_type' => $borrower->id_type,
            'id_verified' => !empty($borrower->id_value),
            'profile_completion' => $this->calculateProfileCompletion($borrower),
            "kyc_link" => $borrower->kyc_link,
            "tos_link" => $borrower->tos_link,
            'tos_status' => $borrower->tos_status,
            'wallet_created' => $borrower->wallet_created,
            'rejection_reasons' => empty($borrower->rejection_reasons) ? [] : json_decode($borrower->rejection_reasons),
        ], 'KYC status retrieved successfully');
    }



    /**
     * @OA\Post(
     *   path="/api/profile/passcode/reset",
     *   tags={"Profile"},
     *   summary="Reset passcode ",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"old_passcode","passcode","passcode_confirmation"},
     *       @OA\Property(property="old_passcode", type="string", example="123456"),
     *       @OA\Property(property="passcode", type="string"),
     *       @OA\Property(property="passcode_confirmation", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Reset"),
     *   @OA\Response(response=400, description="Invalid OTP")
     * )
     */
    public function resetPasscode(Request $request)
    {
        $request->validate([
            'old_passcode'      => 'required|min:4',
            'passcode' => 'required|min:4|confirmed',
            'passcode_confirmation' => 'required|min:4',
        ]);

        $record = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$record || !Hash::check($request->old_passcode, $record->password)) {
            return $this->error('Invalid old passcode', 400);
        }

        $record->password = Hash::make($request->passcode);
        $record->otp = null; // Clear OTP after successful reset
        $record->save();

        return $this->success('Passcode reset successfully');
    }


    /**
     * @OA\Post(
     *   path="/api/profile/pin/reset",
     *   tags={"Profile"},
     *   summary="Reset PIN ",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"old_pin","pin","pin_confirmation"},
     *       @OA\Property(property="old_pin", type="string", example="1234"),
     *       @OA\Property(property="pin", type="string"),
     *       @OA\Property(property="pin_confirmation", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Reset"),
     *   @OA\Response(response=400, description="Invalid or expired OTP")
     * )
     */
    public function resetPin(Request $request)
    {
        $request->validate([
            'old_pin'      => 'required|digits:4',
            'pin' => 'required|digits:4|confirmed',
            'pin_confirmation' => 'required|digits:4',
        ]);

        $record = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$record || !Hash::check($request->old_pin, $record->pin)) {
            return $this->error('Invalid old PIN', 400);
        }

        $record->pin = Hash::make($request->pin);
        $record->otp = null; // Clear OTP after successful reset
        $record->save();

        return $this->success('Pin changed successfully');
    }

        /**
     * @OA\Post(
     *   path="/api/profile/pin/verify",
     *   tags={"Profile"},
     *   summary="PIN Verify ",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"transaction_pin"},
     *       @OA\Property(property="transaction_pin", type="string", example="1234"),
     *     )
     *   ),
     *   @OA\Response(response=200, description="Valid"),
     *   @OA\Response(response=400, description="Invalid or expired OTP")
     * )
     */
    public function ValidatePin(Request $request)
    {
        $request->validate([
            'transaction_pin'      => 'required|digits:4',
        ]);

        $record = Borrower::find(auth()->guard('borrower')->user()->id);

        if (!$record || !Hash::check($request->transaction_pin, $record->pin)) {
            return $this->error('Invalid Pin', 400);
        }

        return $this->success('Pin verified successfully');
    }


    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(Borrower $borrower): int
    {
        $requiredFields = [
            'first_name', 'last_name', 'email', 'phone', 'dob', 
            'address', 'city', 'state', 'zipcode', 'id_value'
        ];

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($borrower->$field)) {
                $completedFields++;
            }
        }

        return round(($completedFields / count($requiredFields)) * 100);
    }
}
