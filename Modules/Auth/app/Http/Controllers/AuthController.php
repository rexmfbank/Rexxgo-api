<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use \App\Models\Company;
use \App\Models\Borrower;
use App\Models\WebhookLog;
use \Modules\Auth\app\Models\PasswordResetToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
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

            $company['walkthrough'] = config('walkthrough',[]);

            return $this->success($company, 'Company details retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

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

    public function register(Request $request): JsonResponse
    {
        $companyId = (int) $request->headers->get('X-Company-ID');
        $branchId = (int) $request->headers->get('X-Branch-ID');

        app()->instance('tenant.company_id', $companyId);

        $request->validate([
            'customer_type' => 'required|string|in:Individual,Business',
            'phone'         => 'required|string|max:15',
            'email'         => 'required|email|max:50',
            'first_name'    => 'required|string|max:50',
            'last_name'     => 'required|string|max:50',
            'middle_name'   => 'nullable|string|max:50',
        ]);

        //check if email or phone already exists for this company
        //if email or phone exists, return error
        if (Borrower::where('company_id', $companyId)
            ->where(function ($q) use ($request) {
                $q->where('phone', $request->input('phone'))
                    ->orWhere('email', $request->input('email'));
            })->exists()) {
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
                'phone_verified_at' => now(), // phone is verified after registration
                'kyc_status'        => 'kyc_pending', // pending until kyc is done
                'status'        => 'active', // pending until passcode is set
            ]);

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
        $view = view("emails.generic", ['msg' => $msg]);
        tribearcSendMail(env("APP_NAME") . " - Your OTP", $view, $email);

        return $this->success([
            'email'      => $request->email,
            'expires_in' => 300,
        ], 'OTP sent successfully.');
    }

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
        $webhookLog = WebhookLog::where('webhook_id', $request->input('verification_id'))
            ->where('source', 'qoreid')
            ->first();
        if (!$webhookLog) {
            $borrower->kyc_status === 'kyc_pending';
            $borrower->save();
            return $this->error('Facial verification pending.', 404);
        }

        // If the webhook log exists, update the borrower status, check the similarity score
        $webhookData = $webhookLog->payload;

        if ($webhookData['summary']['bvn_check']['status'] == 'EXACT_MATCH') {
            $borrower->kyc_status = 'kyc_verified';
            // $borrower->first_name = $webhookData['bvn']['firstname'] ?? 'Anonymous';
            // $borrower->last_name = $webhookData['bvn']['lastname'] ?? 'Anonymous';
            $borrower->middle_name = $webhookData['bvn']['middlename'] ?? $borrower->middle_name;
            $borrower->gender = $webhookData['bvn']['gender'];
            $borrower->dob = Carbon::createFromFormat('d-m-Y', $webhookData['bvn']['birthdate'])->toDateString() ?? null;
            $borrower->photo = $webhookData['metadata']['imageUrl'] ?? null;
        } else {
            $borrower->kyc_status = 'kyc_failed';
        }
        $borrower->save();

        if ($borrower->kyc_status === 'kyc_failed') {
            $data = [
                'kyc_result' => $webhookData['summary']['bvn_check']['fieldMatches']
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

        $data = [
            'email'       => $borrower->email,
            'biometrics' => $borrower->biometrics,
        ];

        return $this->success($data, 'Biometrics updated successfully!');
    }



    public function setPin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:borrowers,email',
            'pin' => 'required|digits:6|confirmed', // uses pin_confirmation field
            'pin_confirmation' => 'required|digits:6',
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

        return $this->success($data, 'PIN set successfully. Registration complete.');
    }




    ///****************************** */



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

        return $this->respondWithToken($token, 'Login successful');
    }

    public function logout()
    {
        Auth::guard('borrower')->logout();
        return $this->success(null, 'Successfully logged out');
    }
    /**
     * Refresh token
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
        $data = [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60, // seconds
        ];

        return $this->success($data, $message);
    }


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


    public function resetPin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:borrowers,email',
            'otp'      => 'required|numeric',
            'pin' => 'required|min:4|confirmed',
            'pin_confirmation' => 'required|min:4',
        ]);

        $record = Borrower::where('email', $request->email)->first();

        //check otp expired
        if($record && $record->otp_expires_at && Carbon::now()->greaterThan($record->otp_expires_at)) {
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

        $view = view("emails.generic", ['msg' => $msg]);
        $email =  $borrower->email;
        tribearcSendMail(env("APP_NAME") . " - Your OTP is here...", $view, $email);

        return $this->success([
            'email' => $borrower->email,
            'expires_in' => 300,
        ], 'OTP sent successfully', 200);
    }








    ///**************************************  */














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