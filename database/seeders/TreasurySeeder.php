<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Treasury;

class TreasurySeeder extends Seeder
{
    public function run(): void
    {
        $treasuries = [
            [
                'name' => 'Naira Treasury',
                'currency' => 'NGN',
                'rates_to_dollar' => 0.0007, // Example: 1 NGN = 0.0007 USD
                'fee_type' => 'percentage',
                'fees_value' => 1.5,
                'fees_capped_at' => 1000,
                'balance' => 0,
                'low_threshold_limit' => 5000,
                'configuration' => json_encode(['country' => 'Nigeria']),
                'status' => 'active',
            ],
            [
                'name' => 'Dollar Treasury',
                'currency' => 'USD',
                'rates_to_dollar' => 1.0,
                'fee_type' => 'percentage',
                'fees_value' => 1.0,
                'fees_capped_at' => 50,
                'balance' => 0,
                'low_threshold_limit' => 100,
                'configuration' => json_encode(['country' => 'United States']),
                'status' => 'active',
            ],
            [
                'name' => 'USDC Treasury',
                'currency' => 'USDC',
                'rates_to_dollar' => 1.0,
                'fee_type' => 'fixed',
                'fees_value' => 0.5,
                'fees_capped_at' => null,
                'balance' => 0,
                'low_threshold_limit' => 10,
                'configuration' => json_encode(['chain' => 'ethereum']),
                'status' => 'active',
            ],
        ];

        foreach ($treasuries as $data) {
            Treasury::updateOrCreate(
                ['currency' => $data['currency']],
                $data
            );
        }
    }
}
