<?php

namespace Modules\Wallet\Services;

use App\Models\SavingsTransaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RexMfbService
{
    public function GetBanks()
    {
        $baseUrl = rtrim(env('REX_MFB_BASEURL'), '/');

        $url = "{$baseUrl}/banks";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->get($url);

        $body = $response->json();
            Log::info($body);

        return $body;
    }

    public function CreateWallet(array $data)
    {
        $baseUrl = rtrim(env('REX_MFB_BASEURL'), '/');
        $url = "{$baseUrl}/create_wallet";


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-client-id' => env('REX_MFB_ClientId'),
            'x-client-secret' => env('REX_MFB_ClientSecret'),
            'x-source-code' => env('REX_MFB_SourceCode'),
            'X-Company-ID' => env('REX_MFB_CompanyId'),
        ])->post($url, $data);

        $body = $response->json();

        return $body;
    }

    public function VerifyAccountNumber(array $data)
    {
        $baseUrl = rtrim(env('REX_MFB_BASEURL'), '/');
        $url = "{$baseUrl}/name-inquiry";


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-client-id' => env('REX_MFB_ClientId'),
            'x-client-secret' => env('REX_MFB_ClientSecret'),
            'x-source-code' => env('REX_MFB_SourceCode'),
            'X-Company-ID' => env('REX_MFB_CompanyId'),
        ])->post($url, $data);

        $body = $response->json();

        return $body;
    }

    public function SendMoney(array $data)
    {
        $baseUrl = rtrim(env('REX_MFB_BASEURL'), '/');
        $url = "{$baseUrl}/send-money";


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-client-id' => env('REX_MFB_ClientId'),
            'x-client-secret' => env('REX_MFB_ClientSecret'),
            'x-source-code' => env('REX_MFB_SourceCode'),
            'X-Company-ID' => env('REX_MFB_CompanyId'),
        ])->post($url, $data);

        $body = $response->json();

        return $body;
    }

    public function SendMoneyInternal(array $data)
    {
        $baseUrl = rtrim(env('REX_MFB_BASEURL'), '/');
        $url = "{$baseUrl}/transfer-internal";


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-client-id' => env('REX_MFB_ClientId'),
            'x-client-secret' => env('REX_MFB_ClientSecret'),
            'x-source-code' => env('REX_MFB_SourceCode'),
            'X-Company-ID' => env('REX_MFB_CompanyId'),
        ])->post($url, $data);

        $body = $response->json();

        return $body;
    }
}
