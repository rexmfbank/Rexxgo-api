<?php

namespace Modules\Wallet\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BridgeService
{
/**
     * Create a Bridge KYC Link.
     *
     * Expected $data keys (flat or nested are accepted):
     * - full_name
     * - email
     * - type (e.g., 'individual' or 'business')
     * - idempotency_key (optional)
     *
     * @param array $data
     * @return array Response body from Bridge
     * @throws \RuntimeException on non-2xx response or network failure
     */
    public function createKycLink(array $data) : ?array
    {
        $payload = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'type' => $data['type'],
        ];

        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');
        // Use provided idempotency_key or generate a new one
        $idempotencyKey = (string)\Illuminate\Support\Str::uuid();
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Api-Key' => $apiKey,
                'Idempotency-Key' => $idempotencyKey,
            ])->acceptJson()
              ->post($baseUrl . '/v0/kyc_links', $payload); 
        } catch (\Throwable $e) {
            
            Log::error('Failed to contact Bridge: ' . $e->getMessage());
        }

        if ($response->failed()) {
            $message = 'Bridge error: ' . $response->status();
            $body = $response->json();
            if (is_array($body)) {
                $message .= ' ' . json_encode($body); 
            } else {
                $message .= ' ' . $response->body(); 
            }
            throw new \RuntimeException($message);
        }

        return $response->json();
    }

}