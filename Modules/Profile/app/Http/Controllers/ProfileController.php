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
use Modules\Profile\app\Http\Resources\UserResource;


class ProfileController extends Controller
{
    use ApiResponse;

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
     * @OA\Put(
     *   path="/api/profile",
     *   tags={"Profile"},
     *   summary="Update user profile",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="country", type="string", example="USA", description="Country code (USA or NG)"),
     *       @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
     *       @OA\Property(property="address", type="string", example="123 Main St"),
     *       @OA\Property(property="city", type="string", example="New York"),
     *       @OA\Property(property="state", type="string", example="NY"),
     *       @OA\Property(property="zipcode", type="string", example="10001"),
     *       @OA\Property(property="ssn", type="string", example="123-45-6789", description="For US users only"),
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
        if (!auth()->guard('borrower')->check()) {
            return $this->error('Unauthorized', 401);
        }

        $borrower = auth()->guard('borrower')->user();
        // Validate based on country
        $country = $borrower->country;
        if ($borrower->country === 'USA') {
            $validationRules = $this->getUSValidationRules();
        } else {
            $validationRules = $this->getNigeriaValidationRules();
        }

        $validatedData = $request->validate($validationRules);

        try {
            // Update basic profile fields
            $updateData = [
                'dob' => $validatedData['dob'] ?? null,
                'address' => $validatedData['address'] ?? null,
                'city' => $validatedData['city'] ?? null,
                'state' => $validatedData['state'] ?? null,
                'zipcode' => $validatedData['zipcode'] ?? null,
            ];

            // Handle country-specific fields
            if ($country === 'USA') {
                $updateData['id_type'] = 'SSN';
                $updateData['id_value'] = $validatedData['ssn'] ?? null;

            } else {
                // For Nigeria, prioritize BVN over NIN
                if (isset($validatedData['bvn'])) {
                    $updateData['id_type'] = 'BVN';
                    $updateData['id_value'] = $validatedData['bvn'];
                } elseif (isset($validatedData['nin'])) {
                    $updateData['id_type'] = 'NIN';
                    $updateData['id_value'] = $validatedData['nin'];
                }

                // Fetch additional details from VerifyMe SDK for Nigeria users
                if (isset($validatedData['bvn']) || isset($validatedData['nin'])) {
                    $verifyMeData = $this->fetchVerifyMeData($validatedData['bvn'] ?? $validatedData['nin'], $updateData['id_type']);
                    if ($verifyMeData) {
                        $updateData = array_merge($updateData, $verifyMeData);
                    }
                }
            }

            DB::table('borrowers')->where('id', $borrower->id)->update($updateData);

            // Refresh borrower instance
            $borrower = Borrower::find($borrower->id);

            Log::info("Profile updated for borrower ID: {$borrower->id}, Country: {$country}");

            return $this->success(new UserResource($borrower), 'Profile updated successfully');

        } catch (\Exception $e) {
            Log::error("Failed to update profile for borrower ID: {$borrower->id}. Error: " . $e->getMessage());
            return $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get validation rules for US users
     */
    private function getUSValidationRules(): array
    {
        return [
            'dob' => 'required|date|before:today',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'zipcode' => 'required|string|max:10',
            'ssn' => 'required|string|regex:/^\d{3}-\d{2}-\d{4}$/',
        ];
    }

    /**
     * Get validation rules for Nigeria users
     */
    private function getNigeriaValidationRules(): array
    {
        return [
            'bvn' => 'required_without:nin|string|size:11|regex:/^\d{11}$/',
            'nin' => 'required_without:bvn|string|size:11|regex:/^\d{11}$/',
        ];
    }

    /**
     * Fetch additional details from VerifyMe SDK for Nigeria users
     */
    private function fetchVerifyMeData(string $idValue, string $idType): ?array
    {
        try {
            $verifyMeApiKey = env('VERIFYME_API_KEY');
            $verifyMeBaseUrl = env('VERIFYME_BASE_URL', 'https://vapi.verifyme.ng/v1');

            if (!$verifyMeApiKey) {
                Log::warning('VerifyMe API key not configured');
                return null;
            }

            $endpoint = $idType === 'BVN' 
                ? "/verifications/identities/bvn/{$idValue}"
                : "/verifications/identities/nin/{$idValue}";
            
            $requestData = [];
            if ($idType === 'NIN') {
                // We need the user's basic info for NIN verification
                $borrower = auth()->guard('borrower')->user();
                $requestData = [
                    'firstname' => $borrower->first_name ?? 'John',
                    'lastname' => $borrower->last_name ?? 'Doe',
                    'dob' => $borrower->dob ? date('d-m-Y', strtotime($borrower->dob)) : '01-01-1990'
                ];
            }
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $verifyMeApiKey,
                'Content-Type' => 'application/json',
            ])->post($verifyMeBaseUrl . $endpoint, $requestData);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'first_name' => $data['data']['firstname'] ?? $data['data']['first_name'] ?? null,
                    'last_name' => $data['data']['lastname'] ?? $data['data']['last_name'] ?? null,
                    'middle_name' => $data['data']['middlename'] ?? $data['data']['middle_name'] ?? null,
                    'dob' => $data['data']['dob'] ?? $data['data']['date_of_birth'] ?? null,
                    'gender' => $data['data']['gender'] ?? null,
                    'phone' => $data['data']['phone'] ?? $data['data']['phone_number'] ?? null,
                    'address' => $data['data']['address'] ?? null,
                    'city' => $data['data']['city'] ?? null,
                    'state' => $data['data']['state'] ?? null,
                ];
            }

            Log::warning("VerifyMe API call failed: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("VerifyMe API error: " . $e->getMessage());
            return null;
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
            'profile_completion' => $this->calculateProfileCompletion($borrower)
        ], 'KYC status retrieved successfully');
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
