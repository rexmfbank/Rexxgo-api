<?php

namespace Modules\Wallet\Services;

use App\Models\SavingsTransaction;
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
    public function createKycLink(array $data): ?array
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
            throw new \RuntimeException('Failed to contact Bridge: ' . $e->getMessage());
        }

        if ($response->failed()) {
            $message = 'Bridge error: ' . $response->status();
            $body = $response->json();
            if (is_array($body)) {
                if (isset($body['existing_kyc_link'])) {
                    $existing = $body['existing_kyc_link'];
                    return [
                        "customer_id" => $existing['customer_id'],
                        "kyc_link" => $existing['kyc_link'],
                        "tos_link" => $existing['tos_link'],
                        "kyc_status" => $existing['kyc_status'],
                        "rejection_reasons" => $existing['rejection_reasons'],
                        "tos_status" => $existing['tos_status'],
                    ];
                } else {
                    $message .= ' ' . json_encode($body);
                }
            } else {
                $message .= ' ' . $response->body();
            }
            throw new \RuntimeException($message);
        }

        return $response->json();
    }


    /**
     * Create a USD Virtual Account for a Bridge Customer.
     *
     * Expected $data keys:
     * - customer_id (string)  => Bridge customer ID (required)
     * - developer_fee_percent (optional, default 0)
     * - idempotency_key (optional)
     *
     * @param array $data
     * @return array|null Response body from Bridge
     * @throws \RuntimeException on non-2xx response or network failure
     */
    public function createUsdWallet(array $data): ?array
    {
        Log::info("Payload: " . json_encode($data));
        $customerId = $data['customer_id'] ?? null;

        if (!$customerId) {
            throw new \InvalidArgumentException('customer_id is required.');
        }

        $payload = [
            'developer_fee_percent' => $data['developer_fee_percent'] ?? '0',
            'source' => [
                'currency' => 'usd',
            ],
            'destination' => [
                'currency' => 'usdc',
                'payment_rail' => 'ethereum',
                'bridge_wallet_id' => $data['wallet_id'],
            ],
        ];


        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');
        $idempotencyKey = $data['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid();

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Api-Key' => $apiKey,
                'Idempotency-Key' => $idempotencyKey,
            ])
                ->acceptJson()
                ->post("{$baseUrl}/v0/customers/{$customerId}/virtual_accounts", $payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to contact Bridge (createUsdWallet): ' . $e->getMessage());
            throw new \RuntimeException('Network error contacting Bridge.');
        }

        if ($response->failed()) {
            $message = 'API error: ' . $response->status();
            $body = $response->json();
            \Illuminate\Support\Facades\Log::error(json_encode($body));

            if (is_array($body)) {
                $message .= ' ' . $body["message"];
            } else {
                $message .= ' ' . $response->body();
            }
            throw new \RuntimeException($message);
        }
        return $response->json();
    }


    public function createUsdcWallet(string $customerId): ?array
    {
        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');
        $idempotencyKey = (string)\Illuminate\Support\Str::uuid();
        $payload = [
            'currency' => 'usdc',
            'chain' => 'ethereum',
        ];

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Api-Key' => $apiKey,
            'Idempotency-Key' => $idempotencyKey,
        ])->acceptJson()
            ->post("{$baseUrl}/v0/customers/{$customerId}/wallets", $payload);

        if ($response->failed()) {
            $body = $response->json();
            Log::info(json_encode($body));
            $message = $body['message'] ?? $response->body();
            throw new \RuntimeException("Bridge error: {$message}");
        }

        return $response->json();
    }

    public function getAllWallets(): ?array
    {
        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Api-Key' => $apiKey,
        ])->acceptJson()
            ->get("{$baseUrl}/v0/wallets?limit=100");

        if ($response->failed()) {
            $body = $response->json();
            \Illuminate\Support\Facades\Log::error('Bridge getVirtualAccount error', $body);
            $message = $body['message'] ?? $response->body();
            throw new \RuntimeException("Bridge error: {$message}");
        }

        return $response->json();
    }

    public function getVirtualAccount(string $customerId, string $virtualAccountId): ?array
    {
        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Api-Key' => $apiKey,
        ])->acceptJson()
            ->get("{$baseUrl}/v0/customers/{$customerId}/virtual_accounts/{$virtualAccountId}");

        if ($response->failed()) {
            $body = $response->json();
            \Illuminate\Support\Facades\Log::error('Bridge getVirtualAccount error', $body);
            $message = $body['message'] ?? $response->body();
            throw new \RuntimeException("Bridge error: {$message}");
        }

        return $response->json();
    }



    /**
     * Transfer USD from a Bridge wallet to an external USD bank account.
     *
     * @param string $walletId Bridge virtual wallet ID (source)
     * @param float $amount Amount in USD
     * @param array $destinationBank [
     *   'account_number' => string,
     *   'routing_number' => string,
     *   'account_holder_name' => string,
     *   'account_type' => checking|savings
     * ]
     * @param string|null $onBehalfOf Optional Bridge customer ID
     * @return array Bridge API response
     */
    public function transferUsdToBank(string $walletId, float $amount, array $destinationBank, ?string $onBehalfOf = null): array
    {
        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'source' => [
                'payment_rail' => 'usd_wallet',
                'currency' => 'usd',
                'account_id' => $walletId,
            ],
            'destination' => [
                'payment_rail' => 'usd_bank',
                'currency' => 'usd',
                'account_details' => [
                    'account_number' => $destinationBank['account_number'],
                    'routing_number' => $destinationBank['routing_number'],
                    'account_holder_name' => $destinationBank['account_holder_name'],
                    'account_type' => $destinationBank['account_type'],
                ],
            ],
        ];

        if ($onBehalfOf) {
            $payload['on_behalf_of'] = $onBehalfOf;
        }

        $idempotencyKey = (string) Str::uuid();
        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');

        $response = Http::withHeaders([
            'Api-Key' => $apiKey,
            'Idempotency-Key' => $idempotencyKey,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/transfers", $payload);

        if (!$response->successful()) {
            Log::error('Bridge transfer error: ' . $response->body() . '-' . 'api kye ' . $apiKey);
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->json(),
            ];
        }
        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }



    public function Transfer(string $customerId, $reference, array $source, array $destination, float $amount)
    {
        $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
        $apiKey = env('BRIDGE_API_KEY');
        $idempotencyKey = (string)\Illuminate\Support\Str::uuid();

        $payload = [
            'source' => $source,
            'destination' => $destination,
            'amount' => (string)$amount,
            "on_behalf_of" => $customerId,
            "client_reference_id" => $reference
        ];

        Log::info(json_encode($payload));
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Api-Key' => $apiKey,
            'Idempotency-Key' => $idempotencyKey,
        ])->acceptJson()
            ->post("{$baseUrl}/v0/transfers", $payload);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['message'] ?? 'Something went wrong';

            if (isset($body['source']['key']) && is_array($body['source']['key'])) {
                foreach ($body['source']['key'] as $key => $msg) {
                    $message = "{$key}: {$msg}";
                    break;
                }
            }
            throw new \RuntimeException($message);
        }

        $data = $response->json();

        return $data;
    }

public function createExternalAccount($customerId, array $data)
{
    $payload = [
        "id" => $data['id'] ?? (string) \Illuminate\Support\Str::uuid(),
        "currency" => strtolower($data['currency']),
        "bank_name" => $data['bank_name'],
        "account_owner_name" => $data['account_owner_name'],
        "account_type" => $data['account_type'],
        "account" => $data['account'],
        "address" => $data['address'],
    ];

    $baseUrl = rtrim(env('BRIDGE_BASE_URL', 'https://api.bridge.xyz'), '/');
    $apiKey = env('BRIDGE_API_KEY');
    $idempotencyKey = (string)\Illuminate\Support\Str::uuid();

    $url = "{$baseUrl}/customers/{$customerId}/external_accounts";

    $response = Http::withHeaders([
        'Api-Key' => $apiKey,
        'Content-Type' => 'application/json',
        'Idempotency-Key' => $idempotencyKey,
    ])->withBody(
        json_encode($payload),
        'application/json'
    )->post($url);

    $body = $response->json();

    if ($response->failed()) {
        \Log::error('Bridge externalAccount error', $body);
        return ["error" => $body['message'] ?? 'Unknown error'];
    }

    return $body;
}

}
