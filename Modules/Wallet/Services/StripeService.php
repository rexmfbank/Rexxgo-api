<?php

namespace Modules\Wallet\Services;

use App\Models\SavingsTransaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Stripe\Stripe;
use Stripe\Customer;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function getOrCreateCustomer($borrower)
    {
        if ($borrower->stripe_customer_id) {
            return $borrower->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $borrower->email,
            'name'  => $borrower->first_name . ' ' . $borrower->last_name,
        ]);

        $borrower->update([
            'stripe_customer_id' => $customer->id
        ]);

        return $customer->id;
    }
}
