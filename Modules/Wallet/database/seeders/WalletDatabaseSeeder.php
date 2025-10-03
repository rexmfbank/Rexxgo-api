<?php

namespace Modules\Wallet\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SavingsProduct;

class WalletDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createSavingsProducts();
    }

    private function createSavingsProducts(): void
    {
        $products = [
            [
                'product_name' => 'NGN',
                'interest_rate_per_annum' => 5.0,
                'minimum_balance_for_interest_rate' => 1000,
                'minimum_balance_for_withdrawal' => 100,
                'allow_savings_overdrawn' => false,
                'allow_duplicate_transactions' => false,
                'interest_method' => 'daily_balance',
                'interest_posting_frequency' => 1,
                'when_should_interest_be_added' => 30,
            ],
            [
                'product_name' => 'USD',
                'interest_rate_per_annum' => 3.0,
                'minimum_balance_for_interest_rate' => 50,
                'minimum_balance_for_withdrawal' => 10,
                'allow_savings_overdrawn' => false,
                'allow_duplicate_transactions' => false,
                'interest_method' => 'daily_balance',
                'interest_posting_frequency' => 1,
                'when_should_interest_be_added' => 30,
                'company_id' => 1,
                'branch_id' => 1,
                'branch_ids' => '1',
            ],
            [
                'product_name' => 'USDC',
                'interest_rate_per_annum' => 4.0,
                'minimum_balance_for_interest_rate' => 50,
                'minimum_balance_for_withdrawal' => 10,
                'allow_savings_overdrawn' => false,
                'allow_duplicate_transactions' => false,
                'interest_method' => 'daily_balance',
                'interest_posting_frequency' => 'monthly',
                'when_should_interest_be_added' => 'end_of_month',
            ],
        ];

        foreach ($products as $productData) {
            SavingsProduct::firstOrCreate(
                [
                    'product_name' => $productData['product_name'],
                ],
                $productData
            );
        }
    }
}
