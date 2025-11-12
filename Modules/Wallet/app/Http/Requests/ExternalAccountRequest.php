<?php
namespace Modules\Wallet\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        return [
            'wallet_id' => 'required|string',
            'currency' => 'required|string|in:USD,usd',
            'bank_name' => 'required|string|max:100',
            'account_owner_name' => 'required|string|max:100',
            'account_type' => 'required|string|in:us',

            'account.account_number' => 'required|digits_between:4,17',
            'account.routing_number' => 'required|digits:9',
            'account.checking_or_savings' => 'required|in:checking,savings',

            'address.street_line_1' => [
                'required', 'string', 'min:3', 'max:35',
                'regex:/\d+/', // must contain a number
                'not_regex:/P\.?O\.?\s?Box/i',
                'not_regex:/PMB/i'
            ],
            'address.street_line_2' => [
                'nullable', 'string', 'max:35',
                'not_regex:/P\.?O\.?\s?Box/i',
                'not_regex:/PMB/i'
            ],
            'address.city' => 'required|string|max:50',
            'address.country' => 'required|string|max:56',
            'address.state' => 'required_if:address.country,USA|string|max:2',
            'address.postal_code' => 'required|string|max:12',

            'narration' => 'required|string|max:150',
            'transaction_pin' => 'required|digits:4',
            'amount' => 'required|numeric|min:1'
        ];
    }
}
