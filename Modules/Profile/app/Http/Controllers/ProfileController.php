<?php

namespace Modules\Profile\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\GenericMail;
use App\Models\Borrower;
use App\Models\Savings;
use App\Models\SavingsProduct;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Profile\app\Http\Resources\LoginActivityResource;
use Modules\Profile\app\Http\Resources\UserResource;
use Modules\Wallet\Services\BridgeService;

use function Symfony\Component\Clock\now;

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
        $user = new UserResource($borrower);
        Log::info(json_encode($user, JSON_PRETTY_PRINT));
        return $this->success(new UserResource($borrower), 'Profile retrieved successfully');
    }
    /**
     * @OA\Post(
     *   path="/api/profile/update",
     *   tags={"Profile"},
     *   summary="Update user profile details",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         @OA\Property(property="first_name", type="string", example="John"),
     *         @OA\Property(property="last_name", type="string", example="Doe"),
     *         @OA\Property(property="middle_name", type="string", example="Michael"),
     *         @OA\Property(property="phone", type="string", example="+2348012345678"),
     *         @OA\Property(property="avatar", type="string", format="binary"),
     *        @OA\Property(
     *             property="gender",
     *             type="string",
     *             enum={"male", "female"},
     *             example="male",
     *             description="Gender must be 'male' or 'female'"
     *         ),
     *         @OA\Property(
     *             property="show_account_balance",
     *             type="string",
     *             example=true,
     *             description="Show or hide account balance"
     *         ),
     *         @OA\Property(
     *             property="allow_notification",
     *             type="string",
     *             example=true,
     *             description="Enable or disable all app notifications"
     *         ),
     *         @OA\Property(
     *             property="updates_notification",
     *             type="string",
     *             example=true,
     *             description="Receive updates/announcement notifications"
     *         ),
     *         @OA\Property(
     *             property="balance_changes_notification",
     *             type="boolean",
     *             example=true,
     *             description="Receive notifications for account balance changes"
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=200, description="Profile updated successfully"),
     *   @OA\Response(response=400, description="Validation error"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'first_name'   => 'nullable|string|max:100',
            'last_name'    => 'nullable|string|max:100',
            'middle_name'  => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'avatar'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'gender' => 'nullable|in:male,female',
            'show_account_balance' => 'nullable',
            'allow_notification' => 'nullable',
            'updates_notification' => 'nullable',
            'balance_changes_notification' => 'nullable',
        ]);

        $user = auth()->guard('borrower')->user();

        try {
            $updateData = [];

            if ($request->filled('first_name'))   $updateData['first_name'] = $validated['first_name'];
            if ($request->filled('last_name'))    $updateData['last_name'] = $validated['last_name'];
            if ($request->filled('middle_name'))  $updateData['middle_name'] = $validated['middle_name'];
            if ($request->filled('phone'))        $updateData['phone'] = $validated['phone'];
            if ($request->filled('gender'))       $updateData['gender'] = $validated['gender'];
            if ($request->filled('show_account_balance')) {
                $updateData['show_account_balance'] = $request->boolean('show_account_balance');
            }
            if ($request->filled('allow_notification')) {
                $updateData['allow_notification'] = $request->boolean('allow_notification');
            }
            if ($request->filled('updates_notification')) {
                $updateData['updates_notification'] = $request->boolean('updates_notification');
            }
            if ($request->filled('balance_changes_notification')) {
                $updateData['balance_changes_notification'] = $request->boolean('balance_changes_notification');
            }


            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $updateData['photo'] = $avatarPath;
            }

            DB::table('borrowers')->where('id', $user->id)->update($updateData);

            // Refresh user
            $user->refresh();

            return $this->success(new UserResource($user), 'Profile updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }




    /**
     * @OA\Post(
     *     path="/api/profile/fcm-token",
     *     summary="Update authenticated user's FCM token",
     *     tags={"Profile"},
     *   security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="fcm_token", type="string", example="fcm_token_here")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="FCM token updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="FCM token updated successfully"),
     *             @OA\Property(property="fcm_token", type="string", example="fcm_token_here")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The fcm_token field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:512',
        ]);
        $borrower = auth()->guard('borrower')->user();

        $updateData = [
            "fcm_token" => $request->fcm_token,
        ];
        DB::table('borrowers')->where('id', $borrower->id)->update($updateData);


        return $this->success("FCM token updated successfully");
    }

    /**
 * @OA\Post(
 *   path="/api/profile/enable-2fa",
 *   tags={"Profile"},
 *   summary="Enable 2FA for the user",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="2FA code sent"),
 *   @OA\Response(response=400, description="Error")
 * )
 */
public function enableTwoFa(Request $request)
{
    $borrower = auth()->guard('borrower')->user();

    if ($borrower->two_fa_enabled) {
        return $this->error('2FA is already enabled', 400);
    }

    $twoFaCode = rand(100000, 999999);
    $borrower->two_fa_code = $twoFaCode;
    $borrower->two_fa_expires_at = now()->addMinutes(10); // code valid for 10 mins
    $borrower->save();

    // Send OTP via email
     //define the email message for the otp
    $msg = "Hello {$borrower->first_name},\n\n";
    $msg .= "You are requesting to enable Two-Factor Authentication (2FA) on your account. ";
    $msg .= "Please use the following OTP to complete the setup: **{$twoFaCode}**.\n\n";
    $msg .= "This OTP will expire in 5 minutes for security purposes.\n\n";
    $msg .= "If you did not request this, please ignore this email. No changes will be made to your account.\n\n";
    $msg .= "Thank you for keeping your account secure,\n";
    $msg .= env('APP_NAME') . " Team";

    $email = $borrower->email;

    Mail::to($email)->send(new GenericMail($msg, env("APP_NAME") . " - Verify Your 2FA Setup"));

    return $this->success([
        'message' => '2FA code sent to your email. Use it to verify and enable 2FA.'
    ], '2FA code sent');
}

/**
 * @OA\Post(
 *   path="/api/profile/verify-enable-2fa",
 *   tags={"Profile"},
 *   summary="Verify 2FA OTP to enable 2FA",
 *   security={{"bearerAuth":{}}},
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       @OA\Property(property="otp", type="string", example="123456")
 *     )
 *   ),
 *   @OA\Response(response=200, description="2FA enabled successfully"),
 *   @OA\Response(response=400, description="Invalid or expired OTP")
 * )
 */
public function verifyEnableTwoFa(Request $request)
{
    $request->validate([
        'otp' => 'required|string|size:6'
    ]);

    $borrower = auth()->guard('borrower')->user();

    if (!$borrower->two_fa_code || Carbon::create($borrower->two_fa_expires_at)->isPast()) {
        return $this->error('OTP expired or invalid', 400);
    }

    if ($borrower->two_fa_code !== $request->otp) {
        return $this->error('Invalid OTP', 400);
    }

    $borrower->two_fa_enabled = true;
    $borrower->two_fa_code = null;
    $borrower->two_fa_expires_at = null;
    $borrower->save();

    return $this->success([
        'message' => 'Two-factor authentication enabled successfully'
    ], '2FA enabled');
}


    /**
 * @OA\Get(
 *   path="/api/profile/login-activities",
 *   tags={"Profile"},
 *   summary="Get login activities of authenticated user",
 *   security={{"bearerAuth":{}}},
 *   @OA\Parameter(
 *      name="page",
 *      in="query",
 *      description="Page number",
 *      required=false,
 *      @OA\Schema(type="integer", default=1)
 *   ),
 *   @OA\Parameter(
 *      name="pageSize",
 *      in="query",
 *      description="Number of items per page",
 *      required=false,
 *      @OA\Schema(type="integer", default=15)
 *   ),
 *   @OA\Response(response=200, description="Success"),
 *   @OA\Response(response=401, description="Unauthorized")
 * )
 */
public function getLoginActivities(Request $request)
{
    Log::info(json_encode(now()));
    $borrower = auth()->guard('borrower')->user();
    $perPage = $request->query('pageSize', 15);

    $activities = \App\Models\LoginActivity::where('borrower_id', $borrower->id)
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

    $resourceCollection = LoginActivityResource::collection($activities);

    $meta = [
        'current_page' => $activities->currentPage(),
        'total_pages'  => $activities->lastPage(),
        'total_items'  => $activities->total(),
        'per_page'     => $activities->perPage(),
    ];
    return $this->success([
        'items' => $resourceCollection,
        'meta'  => $meta,
    ], 'Login activities retrieved successfully', 200);
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

            if ($borrower->kyc_status != "active") {

                if ($borrower->bridge_customer_id !== "" && $borrower->kyc_link != "") {
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
        $tokenUrl = env('QOREID_BASEURL') . '/token';

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
            'first_name',
            'last_name',
            'email',
            'phone',
            'dob',
            'address',
        ];

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($borrower->$field)) {
                $completedFields++;
            }
        }
        $requiredFields = [...$requiredFields, 'tos_status'];
        $requiredFields = [...$requiredFields, 'kyc_status'];

        if($borrower->tos_status === 'approved'){
            $completedFields++;
        }
        if($borrower->kyc_status === 'active'){
            $completedFields++;
        }
        
        $usdWallet = Savings::where('borrower_id', $borrower->id)->where('currency', SavingsProduct::$usd)->first(); //if usd wallet is created, that means wallets has bee n created

        $profileCompletion = round(($completedFields / count($requiredFields)) * 100);

        if($usdWallet){
            if(!empty($usdWallet->account_number)){
                $profileCompletion += 25;
            }
        }
        return $profileCompletion > 100 ? 100 : $profileCompletion;
    }
}
