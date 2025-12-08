<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\GenericMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use \App\Models\Company;
use \App\Models\Borrower;
use App\Models\LoginActivity;
use App\Models\WebhookLog;
use \Modules\Auth\app\Models\PasswordResetToken;
use Carbon\Carbon;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Services\FirebaseService;
use Modules\Wallet\app\Http\Controllers\WalletController;
use Modules\Wallet\Services\BridgeService;
use Modules\Wallet\Services\RexMfbService;

class AuthController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/auth/company",
     *   tags={"Auth"},
     *   summary="Get company info",
     *   @OA\Parameter(name="X-Company-ID", in="header", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=404, description="Not Found")
     * )
     */
    public function getCompany(Request $request): JsonResponse
    {
        try {
            $companyId = $request->header('X-Company-ID');
            if (!$companyId) {
                return $this->error('Company ID not provided', 400);
            }

            $company = Company::select('company_name', 'company_logo', 'website')
                ->where('id', $companyId)
                ->first();

            if (!$company) {
                return $this->error('Company not found', 404);
            }

            $company['walkthrough'] = config('walkthrough', []);

            return $this->success($company, 'Company details retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *   path="/api/auth/countries",
     *   tags={"Auth"},
     *   summary="List supported countries",
     *   @OA\Response(response=200, description="Success")
     * )
     */
    public function getCountries(): JsonResponse
    {
        $countries = [
            ["code" => "NG", "name" => "Nigeria"],
            ["code" => "US", "name" => "United States"],
            ["code" => "GB", "name" => "United Kingdom"],
            ["code" => "CA", "name" => "Canada"],
            ["code" => "GH", "name" => "Ghana"],
            ["code" => "KE", "name" => "Kenya"],
            ["code" => "ZA", "name" => "South Africa"],
            // Add more as needed...
        ];

        return $this->success($countries, 'Countries retrieved successfully');
    }

    /**
     * @OA\Post(
     *   path="/api/auth/register",
     *   tags={"Auth"},
     *   summary="Register a borrower",
     *   @OA\RequestBody(
     *       required=true,
     *       @OA\JsonContent(
     *           required={"customer_type","phone","email","first_name","last_name","country"},
     *           @OA\Property(property="customer_type", type="string", example="Individual"),
     *           @OA\Property(property="phone", type="string", example="08012345678"),
     *           @OA\Property(property="email", type="string", example="john@example.com"),
     *           @OA\Property(property="first_name", type="string", example="John"),
     *           @OA\Property(property="last_name", type="string", example="Doe"),
     *           @OA\Property(property="country", type="string", example="USA"),
     *           @OA\Property(property="middle_name", type="string", example="Q")
     *       )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=409, description="Conflict"),
     *   @OA\Response(response=422, description="Unprocessable Entity")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $companyId = (int) $request->headers->get('X-Company-ID');
        $branchId = (int) $request->headers->get('X-Branch-ID');

        app()->instance('tenant.company_id', $companyId);

       
        try {
            $request->validate([
            'customer_type' => 'required|string|in:Individual,Business',
                'phone'         => 'required|string|max:15',
                'email'         => 'required|email|max:50',
                'first_name'    => 'required|string|max:50',
                'last_name'     => 'required|string|max:50',
                'country' => 'required|string|in:NG,US,UK,GB,CA,GH,KE,ZA,SA',
                'middle_name'   => 'nullable|string|max:50',
            ]);
            // $data = $request->validated();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::info("getting here");
            Log::info($request->all());
            $errors = $e->validator->errors()->all();
            return $this->error($errors[0]);
        }

        //check if email or phone already exists for this company
        //if email or phone exists, return error
        if (Borrower::where('company_id', $companyId)
            ->where(function ($q) use ($request) {
                $q->where('phone', $request->input('phone'))
                    ->orWhere('email', $request->input('email'));
            })->exists()
        ) {
            return $this->error('Phone or Email already exists', 409);
        }


        //check email is verified
        $emailRecord = PasswordResetToken::where('email', $request->email)
            ->where('verified', 'verified')
            ->first();
        if (!$emailRecord) {
            return $this->error('Email not verified. Please verify your email before registering.', 422);
        }

        return DB::transaction(function () use ($request, $companyId, $branchId) {
            $payload = [
                'customer_type' => $request->string('customer_type'),
                'phone'         => $request->string('phone'),
                'email'         => $request->input('email'),
                'password'      => Hash::make($request->input('password')),
                'first_name'    => $request->string('first_name'),
                'last_name'     => $request->string('last_name'),
                'country'       => $request->string('country'),
                'middle_name'   => $request->string('middle_name') ?? null,
            ];

            $existing = Borrower::where('company_id', $companyId)
                ->where(function ($q) use ($payload) {
                    $q->where('phone', $payload['phone'])
                        ->orWhere('email', $payload['email']);
                })
                ->first();

            if ($existing) {
                if ($existing->phone == $payload['phone']) {
                    return $this->error('Phone number already exists', 422);
                }
                if ($existing->email == $payload['email']) {
                    return $this->error('Email already exists', 422);
                }
            }

            $borrower = Borrower::create([
                'company_id'    => $companyId,
                'branch_id'     => $branchId,
                'customer_type' => $payload['customer_type'],
                'phone'         => $payload['phone'],
                'email'         => $payload['email'],
                'first_name'    => $payload['first_name'],
                'middle_name'   => $payload['middle_name'] ?? null,
                'last_name'     => $payload['last_name'],
                'unique_number' => $payload['phone'],
                'country' => $payload['country'],
                'phone_verified_at' => now(), // phone is verified after registration
                'kyc_status'        => 'kyc_pending', // pending until kyc is done
                'status'        => 'active', // pending until passcode is set
            ]);

            // Create savings accounts for NGN, USD, USDC
            $bridgeService = new BridgeService();
            $rexBank = new RexMfbService();

            $walletController = new WalletController($bridgeService, $rexBank);
            $savingsResult = $walletController->createUserWallets($borrower->id);

            // Log the result
            if ($savingsResult['success']) {
            } else {
            }

            $data = [
                'borrower_id'   => Crypt::encryptString($borrower->id),
                'phone'         => $borrower->phone,
                'status'        => $borrower->status,
                'email'         => $borrower->email,
                'first_name'       => $borrower->first_name,
                'last_name'      => $borrower->last_name,
                'middle_name'   => $borrower->middle_name,
                'customer_type' => $borrower->customer_type,
            ];

            return $this->success($data, 'Registration successful.', 201);
        });
    }

    /**
     * @OA\Post(
     *   path="/api/auth/sendotp",
     *   tags={"Auth"},
     *   summary="Send OTP to email",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email"},
     *       @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OTP sent"),
     *   @OA\Response(response=404, description="Not Found"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('company_id');
        $email     = $request->input('email') ?? null;

        if (!$email) {
            return $this->error('Email is required.', 422);
        }

        $borrower = Borrower::where('company_id', $companyId)
            ->where('email', $email)
            ->first();

        if ($borrower && $borrower->phone_verified_at) {
            return $this->error('Email already verified.', 409);
        }

        $otp = random_int(100000, 999999);
        $expiry = Carbon::now()->addMinutes(10); // OTP valid for 10 minutes

        // Save to DB
        PasswordResetToken::updateOrCreate(
            ['email' => $request->email],
            [
                'token' => $otp,
                'updated_at' => $expiry
            ]
        );

        // Example OTP email
        $msg  = 'Please use the following OTP to verify your Email: ' . $otp . ' (expires in 5 minutes).';
        $msg .= "\n\nIf you did not request this, ignore this message.";
        Mail::to($email)->send(new GenericMail($msg, env("APP_NAME") . " - Your OTP"));

        return $this->success([
            'email'      => $request->email,
            'expires_in' => 300,
        ], 'OTP sent successfully.');
    }

    /**
     * @OA\Post(
     *   path="/api/auth/verifyotp",
     *   tags={"Auth"},
     *   summary="Verify OTP",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","otp"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="otp", type="string", example="123456")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Verified"),
     *   @OA\Response(response=422, description="Invalid or expired OTP")
     * )
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|max:50',
            'otp'   => 'required|digits:6',
        ]);

        $record = PasswordResetToken::where('email', $request->email)
            ->where('token', $request->otp)
            ->where('updated_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        $record->verified = 'verified';
        $record->save();


        return $this->success([
            'email' => $request->email,
        ], 'Email verified successfully.');
    }


    /**
     * @OA\Post(
     *   path="/api/auth/facialid",
     *   tags={"Auth"},
     *   summary="Submit facial verification id",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","verification_id"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="verification_id", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="KYC status returned"),
     *   @OA\Response(response=400, description="Precondition failed"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function facialId(Request $request): JsonResponse
    {
        $companyId = (int) $request->headers->get('X-Company-ID');

        $request->validate([
            'email'            => 'required|string|max:50',
            'verification_id'  => 'required|string|max:50',
        ]);

        $borrower = Borrower::query()
            ->where('company_id', $companyId)
            ->where('email', $request->input('email'))
            ->first();

        if (!$borrower) {
            return $this->error('Customer not found for this company.', 404);
        }

        if (!$borrower->phone_verified_at) {
            return $this->error('Email must be verified before facial ID.', 400);
        }

        $borrower->facial_verification_id = $request->input('verification_id');

        //find the webkook payload in the webhook log table
        // $webhookLog = Webhook
        //     ->where('source', 'qoreid')
        //     ->first();
        // if (!$webhookLog) {
        //     $borrower->kyc_status === 'kyc_pending';
        //     $borrower->save();
        //     return $this->error('Facial verification pending.', 404);
        // }

        // // If the webhook log exists, update the borrower status, check the similarity score
        // $webhookData = $webhookLog->payload;

        // if ($webhookData['summary']['bvn_check']['status'] == 'EXACT_MATCH') {
        //     $borrower->kyc_status = 'kyc_verified';
        //     // $borrower->first_name = $webhookData['bvn']['firstname'] ?? 'Anonymous';
        //     // $borrower->last_name = $webhookData['bvn']['lastname'] ?? 'Anonymous';
        //     $borrower->middle_name = $webhookData['bvn']['middlename'] ?? $borrower->middle_name;
        //     $borrower->gender = $webhookData['bvn']['gender'];
        //     $borrower->dob = Carbon::createFromFormat('d-m-Y', $webhookData['bvn']['birthdate'])->toDateString() ?? null;
        //     $borrower->photo = $webhookData['metadata']['imageUrl'] ?? null;
        // } else {
        //     $borrower->kyc_status = 'kyc_failed';
        // }
        $borrower->save();

        if ($borrower->kyc_status === 'kyc_failed') {
            $data = [
                'kyc_result' => "" //$webhookData['summary']['bvn_check']['fieldMatches']
            ];
            return $this->success($data, 'KYC verification failed.', 409);
        }


        // Normally, you’d trigger the QoreID API here to start processing if needed
        $data = [
            'first_name' => $borrower->first_name,
            'last_name' => $borrower->last_name,
            'phone' => $borrower->phone,
            'email' => $borrower->email,
            'gender' => $borrower->gender,
            'dob' => $borrower->dob,
            'kyc_status' => $borrower->kyc_status,
            'matches' => $webhookData['summary']['bvn_check']['fieldMatches'] ?? null,
            'photo' => $borrower->photo,
        ];
        return $this->success($data, 'Facial verification Completed.');
    }


    public function loginView()
    {
        return $this->error('Please Login to access this resource', 'Login Required', 401);
    }

    /**
     * @OA\Post(
     *   path="/api/auth/setpasscode",
     *   tags={"Auth"},
     *   summary="Set passcode",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","passcode","confirm_passcode"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="passcode", type="string", example="123456"),
     *       @OA\Property(property="confirm_passcode", type="string", example="123456")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Passcode set"),
     *   @OA\Response(response=409, description="Already set"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function setPasscode(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:borrowers,email',
            'passcode' => 'required|digits:6', // uses pin_confirmation field
            'confirm_passcode' => 'required|digits:6|same:passcode',
        ]);

        $borrower = Borrower::where('email', $request->email)->first();

        if (!$borrower) {
            return $this->error('Customer not found', 404);
        }

        //check if pin already set
        if ($borrower->password) {
            return $this->error('Passcode already set. Please login.', 409);
        }


        $borrower->password = Hash::make($request->passcode);
        $borrower->status = 'active';
        $borrower->save();
        //data should be access token and borrower details
        //login the borrower
        $token = auth('borrower')->login($borrower);
        DB::table('borrowers')->where('id', $borrower->id)->update(['passcode_created' => true]);
        $data = [
            'borrower_id' => Crypt::encryptString($borrower->id),
            'phone'       => $borrower->phone,
            'status'      => $borrower->status,
            'first_name'  => $borrower->first_name,
            'last_name'   => $borrower->last_name,
            'email'       => $borrower->email,
            'customer_type' => $borrower->customer_type,
            $token ? 'access_token' : 'error' => $token ?: 'Login failed, check credentials',
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60, // seconds
        ];

        return $this->success($data, 'Passcode set successfully!');
    }


    /**
     * @OA\Post(
     *   path="/api/auth/biometrics",
     *   tags={"Auth"},
     *   summary="Enable/disable biometrics",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","biometrics"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="biometrics", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(response=200, description="Updated"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function setBiometrics(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:borrowers,email',
            'biometrics' => 'required|boolean',
        ]);

        $borrower = Borrower::where('email', $request->email)->first();

        if (!$borrower) {
            return $this->error('Customer not found', 404);
        }

        $borrower->biometrics = $request->biometrics;
        $borrower->save();
        DB::table('borrowers')->where('id', $borrower->id)->update(['biometrics_created' => true]);

        $data = [
            'email'       => $borrower->email,
            'biometrics' => $borrower->biometrics,
        ];
        //here //
        return $this->success($data, 'Biometrics updated successfully!');
    }



    /**
     * @OA\Post(
     *   path="/api/auth/setpin",
     *   tags={"Auth"},
     *   summary="Set transaction PIN",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","pin","pin_confirmation"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="pin", type="string", example="1234"),
     *       @OA\Property(property="pin_confirmation", type="string", example="1234")
     *     )
     *   ),
     *   @OA\Response(response=200, description="PIN set"),
     *   @OA\Response(response=409, description="Already set"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function setPin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:borrowers,email',
            'pin' => 'required|digits:4|confirmed', // uses pin_confirmation field
            'pin_confirmation' => 'required|digits:4',
        ]);

        $borrower = Borrower::where('email', $request->email)->first();

        if (!$borrower) {
            return $this->error('Customer not found', 404);
        }

        //check if pin already set
        if ($borrower->pin) {
            return $this->error('PIN already set. Please login.', 409);
        }

        $borrower->pin = Hash::make($request->pin);
        $borrower->save();

        $data = [
            'first_name'  => $borrower->first_name,
            'last_name'   => $borrower->last_name,
            'email'       => $borrower->email,
        ];
        DB::table('borrowers')->where('id', $borrower->id)->update(['pin_created' => true]);
        return $this->success($data, 'PIN set successfully. Registration complete.');
    }

    /**
     * @OA\Get(
     *   path="/api/auth/email",
     *   tags={"Auth"},
     *   summary="Get borrower name by email",
     *   @OA\Parameter(name="email", in="query", required=true, @OA\Schema(type="string", format="email")),
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function getEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:borrowers,email',
        ]);

        $borrower = \App\Models\Borrower::where('email', $request->email)->first();

        if (!$borrower) {
            return $this->error('Borrower not found', 404);
        }

        return $this->success([
            'first_name' => $borrower->first_name,
            'last_name' => $borrower->last_name,
        ], 'Borrower details retrieved successfully', 200);
    }


    ///****************************** */

    /**
     * @OA\Post(
     *   path="/api/auth/login",
     *   tags={"Auth"},
     *   summary="Login",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", example="john@example.com"),
     *       @OA\Property(property="password", type="string", example="secret")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function login(Request $request)
    {
        //validate email and password
        $request->validate([
            'email'    => 'required|email|max:50',
            'password' => 'required|string|min:4|max:50',
        ]);


        $credentials = $request->only('email', 'password');

        if (! $token = Auth::guard('borrower')->attempt($credentials)) {
            return $this->error('Invalid email or password', 401);
        }

        $borrower = Auth::guard('borrower')->user();

        //check for kyc status
        if (!empty($borrower->bridge_customer_id) && $borrower->kyc_status != 'active') {
            $bridgeService = new BridgeService();
            // $bridgeUser = $bridgeService->getCustomer("97ea8018-669e-418e-a24c-d7fa4393095c");
            $bridgeUser = $bridgeService->getCustomer($borrower->bridge_customer_id);
            $kycStatus = $bridgeUser['status'] ?? 'not_started';
            if ($kycStatus == "approved" || $kycStatus == "active") {
                $borrower->kyc_status = "active";
            } elseif ($kycStatus == "not_started") {
            } elseif ($kycStatus == "under_review") {
                $borrower->kyc_status = "awaiting_approval";
            } elseif ($kycStatus == "rejected") {
                $borrower->kyc_status = "rejected";
            } else {
                $borrower->kyc_status = $kycStatus;
            }
            $borrower->rejection_reasons = json_encode($bridgeUser['rejection_reasons'], JSON_PRETTY_PRINT);
            $borrower->tos_status = $bridgeUser['has_accepted_terms_of_service'] ?? $borrower->tos_status;
            $borrower->save();
        }


        if ($borrower->two_fa_enabled) {
            $twoFaCode = rand(100000, 999999); // 6-digit code
            $borrower->two_fa_code = $twoFaCode;
            $borrower->two_fa_expires_at = now()->addMinutes(5); // 5 min expiry
            $borrower->save();

            $msg = "Hello {$borrower->first_name},\n\n";
            $msg .= "A login attempt to your account requires Two-Factor Authentication (2FA). ";
            $msg .= "Please use the following OTP to complete your login: **{$twoFaCode}**.\n\n";
            $msg .= "This OTP will expire in 5 minutes for security purposes.\n\n";
            $msg .= "If you did not attempt to login, please ignore this email.\n\n";
            $msg .= "Thank you for securing your account,\n";
            $msg .= env('APP_NAME') . " Team";

            // Send the OTP email
            $email = $borrower->email;
            Mail::to($email)->send(new GenericMail($msg, env("APP_NAME") . " - Your Login 2FA OTP"));

            return $this->success([
                'user_id' => base64_encode($borrower->id),
                'message' => '2FA code sent. Please verify to complete login.'
            ], '2FA required', 200);
        }

        LoginActivity::create([
            'borrower_id' => $borrower->id,
            'email'       => $request->email,
            'ip_address'  => $request->ip(),
            'device'      => $request->userAgent(),
            "country" => $this->getUserCountry()
        ]);


        return $this->respondWithToken($token, 'Login successful');
    }


    /**
 * @OA\Post(
 *   path="/api/auth/check-email",
 *   tags={"Auth"},
 *   summary="Check if an email already exists",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"email"},
 *       @OA\Property(property="email", type="string", example="john@example.com")
 *     )
 *   ),
 *   @OA\Response(response=200, description="Email check response"),
 * )
 */
public function checkEmail(Request $request)
{
    $request->validate([
        'email' => 'required|email|max:50'
    ]);

    $exists = Borrower::where('email', $request->email)->first();

    if ($exists) {
        return $this->error('Email already exists', 409);
    }

    return $this->success([
        "available" => true,
        "message"   => "Email is available"
    ]);
}



    /**
     * @OA\Post(
     *   path="/api/auth/verify-2fa",
     *   tags={"Auth"},
     *   summary="Verify 2FA code",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="user_id", type="string", example=""),
     *       @OA\Property(property="two_fa_code", type="string", example="123456")
     *     )
     *   ),
     *   @OA\Response(response=200, description="2FA verified, login successful"),
     *   @OA\Response(response=400, description="Invalid or expired 2FA code")
     * )
     */
    public function verifyTwoFa(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string',
            'two_fa_code' => 'required|string|size:6',
        ]);

        $borrower = Borrower::where("id", base64_decode($request->user_id))->first();
        if (!$borrower) {
            return $this->error('Invalid user', 400);
        }

        if (!$borrower->two_fa_enabled || !$borrower->two_fa_code) {
            return $this->error('2FA not enabled for this user', 400);
        }

        if (Carbon::create($borrower->two_fa_expires_at)->isPast()) {
            return $this->error('2FA code expired', 400);
        }

        if ($borrower->two_fa_code !== $request->two_fa_code) {
            return $this->error('Invalid 2FA code', 400);
        }

        // Clear 2FA fields
        $borrower->two_fa_code = null;
        $borrower->two_fa_expires_at = null;
        $borrower->save();

        // Generate JWT token
        $token = Auth::guard('borrower')->login($borrower);

        return $this->respondWithToken($token, 'Login successful');
    }


    /**
     * @OA\Post(
     *   path="/api/auth/logout",
     *   tags={"Auth"},
     *   summary="Logout",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout()
    {
        Auth::guard('borrower')->logout();
        return $this->success(null, 'Successfully logged out');
    }
    /**
     * Refresh token
     */
    /**
     * @OA\Post(
     *   path="/api/auth/refresh",
     *   tags={"Auth"},
     *   summary="Refresh token",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Token refreshed"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function refresh()
    {
        try {
            $newToken = Auth::guard('borrower')->refresh();
            return $this->respondWithToken($newToken, 'Token refreshed');
        } catch (\Exception $e) {
            return $this->error('Token refresh failed', 401, $e->getMessage());
        }
    }

    /**
     * Format token response
     */
    protected function respondWithToken($token, $message = 'Success')
    {
        $user = Auth::guard('borrower')->user();
         $user->photo = $user->photo ? url($user->photo) : null;
        $user->avatar = $user->photo ? url($user->photo) : null;
        $data = [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60, // seconds
            'user' => $user
        ];

        return $this->success($data, $message);
    }


    /**
     * @OA\Post(
     *   path="/api/auth/passcode/reset",
     *   tags={"Auth"},
     *   summary="Reset passcode with OTP",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","otp","passcode","passcode_confirmation"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="otp", type="string", example="123456"),
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
            'email'    => 'required|email|exists:borrowers,email',
            'otp'      => 'required|numeric',
            'passcode' => 'required|min:4|confirmed',
            'passcode_confirmation' => 'required|min:4',
        ]);

        $record = Borrower::where('email', $request->email)->first();

        if (!$record || base64_decode($record->otp) !== $request->otp) {
            return $this->error('Invalid OTP', 400);
        }

        $record->password = Hash::make($request->passcode);
        $record->otp = null; // Clear OTP after successful reset
        $record->save();

        return $this->success('Passcode reset successfully');
    }


    /**
     * @OA\Post(
     *   path="/api/auth/pin/reset",
     *   tags={"Auth"},
     *   summary="Reset PIN with OTP",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","otp","pin","pin_confirmation"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="otp", type="string", example="123456"),
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
            'email'    => 'required|email|exists:borrowers,email',
            'otp'      => 'required|numeric',
            'pin' => 'required|digits:4|confirmed',
            'pin_confirmation' => 'required|digits:4',
        ]);

        $record = Borrower::where('email', $request->email)->first();

        //check otp expired
        if ($record && $record->otp_expires_at && Carbon::now()->greaterThan($record->otp_expires_at)) {
            return $this->error('OTP has expired. Please request a new one.', 400);
        }

        if (!$record || base64_decode($record->otp) !== $request->otp) {
            return $this->error('Invalid OTP', 400);
        }

        $record->pin = Hash::make($request->pin);
        $record->otp = null; // Clear OTP after successful reset
        $record->save();

        return $this->success('Pin changed successfully');
    }



    /**
     * @OA\Post(
     *   path="/api/auth/otp/reset/send",
     *   tags={"Auth"},
     *   summary="Send OTP for reset",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email"},
     *       @OA\Property(property="email", type="string", format="email")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OTP sent"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:borrowers,email',
        ]);

        $borrower = Borrower::where('email', $request->email)->first();
        if (!$borrower) {
            return $this->error('Customer not found', 404);
        }
        // Generate OTP (e.g. 6 digits)
        $otp = rand(100000, 999999);

        // Save OTP (you can use a password_resets table or store directly in DB/Redis)
        $borrower->otp = base64_encode($otp);
        $borrower->otp_expires_at = now()->addMinutes(5); // OTP valid for 5 minutes
        $borrower->save();

        //define the email message for the otp
        $msg = 'Please use the following OTP to verify your phone number: ' . $otp . '. It will expire in 5 minutes.';
        $msg .= "\n\nIf you did not request this, please ignore this message.";
        $msg .= "\n\nThank you for using our service.";

        $email =  $borrower->email;
        Mail::to($email)->send(new GenericMail($msg, env("APP_NAME") . " - Your OTP is here..."));
        
        return $this->success([
            'email' => $borrower->email,
            'expires_in' => 300,
        ], 'OTP sent successfully', 200);
    }




    /**
     * @OA\Post(
     *   path="/api/auth/otp/reset/verify",
     *   tags={"Auth"},
     *   summary="Verify Reset OTP",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","otp"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="otp", type="string", example="123456"),
     *     )
     *   ),
     *   @OA\Response(response=200, description="Reset"),
     *   @OA\Response(response=400, description="Invalid or expired OTP")
     * )
     */
    public function otpResetVerify(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:borrowers,email',
            'otp'      => 'required|numeric',
        ]);

        $record = Borrower::where('email', $request->email)->first();

        //check otp expired
        if ($record && $record->otp_expires_at && Carbon::now()->greaterThan($record->otp_expires_at)) {
            return $this->error('OTP has expired. Please request a new one.', 400);
        }

        if (!$record || base64_decode($record->otp) !== $request->otp) {
            return $this->error('Invalid OTP', 400);
        }

        return $this->success('OTP verified successfully');
    }



    ///**************************************  */










    private function getUserCountry(): string
    {
        try {
            $ip = request()->ip();
            if ($ip === '127.0.0.1') {
                return 'NG'; // localhost fallback
            }

            $response = Http::timeout(3)->get("https://ipapi.co/{$ip}/json/");
            Log::info($response);
            if ($response->successful() && isset($response['country'])) {
                return $response['country']; // e.g. "NG"
            }

            return 'NG';
        } catch (\Throwable $th) {
            return "NG";
        }
    }




    private function success($data, $message = 'Success', $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    private function error($message = 'Error', $code = 400): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
        ], $code);
    }
}
