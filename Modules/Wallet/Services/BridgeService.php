<?php

namespace Modules\Wallet\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class BridgeService
{
    /**
     * Create a Bridge customer.
     *
     * Expected $data keys (flat or nested are accepted):
     * - first_name, last_name, email
     * - birth_date (YYYY-MM-DD)
     * - tax_identification_number
     * - address: [street_line_1, street_line_2, city, state, postal_code, country]
     *   or flat keys: street_line_1, street_line_2, city, state, postal_code, country
     * - idempotency_key (optional)
     *
     * @param array $data
     * @return array Response body from Bridge
     * @throws \RuntimeException on non-2xx response
     */
    public function createCustomer(array $data)
    {
        $payload = $this->mapCustomerPayload($data);

        $baseUrl = rtrim(config('services.bridge.base_url', 'https://api.bridge.xyz'), '/');
        $apiKey = config('services.bridge.api_key');
        $idempotencyKey = $payload['idempotency_key'] ?? Str::uuid()->toString();
        unset($payload['idempotency_key']);

        if (empty($apiKey)) {
            throw new \RuntimeException('Bridge API key is not configured. Set BRIDGE_API_KEY in environment.');
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Idempotency-Key' => $idempotencyKey,
            ])->acceptJson()
              ->post($baseUrl . '/v1/customers', $payload);
        } catch (Throwable $e) {
            throw new \RuntimeException('Failed to contact Bridge: ' . $e->getMessage(), 0, $e);
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

    /**
     * Normalize incoming data to Bridge payload shape.
     *
     * @param array $data
     * @return array
     */
    private function mapCustomerPayload(array $data)
    {
        $address = Arr::get($data, 'address', []);

        $payload = [
            'first_name' => Arr::get($data, 'firstName'),
            'last_name' => Arr::get($data, 'lastName'),
            'email' => Arr::get($data, 'email'),
            'birth_date' => Arr::get($data, 'birthDate'),
            'tax_identification_number' => Arr::get($data, 'taxIdentificationNumber'),
            'address' => [
                'street_line_1' => Arr::get($address, 'streetLine1'),
                'street_line_2' => Arr::get($address, 'streetLine2'),
                'city' => Arr::get($address, 'city'),
                'state' => Arr::get($address, 'state'),
                'postal_code' => Arr::get($address, 'postalCode'),
                'country' => Arr::get($address, 'country'),
            ],
        ];

        if (Arr::has($data, 'idempotencyKey')) {
            $payload['idempotency_key'] = Arr::get($data, 'idempotencyKey');
        }

        return $payload;
    }
}