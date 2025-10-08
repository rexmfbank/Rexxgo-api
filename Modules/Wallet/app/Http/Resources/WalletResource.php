<?php

namespace Modules\Wallet\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'borrower_id' => $this->borrower_id,
            'savings_product_id' => $this->savings_product_id,
            'available_balance' => $this->available_balance,
            'ledger_balance' => $this->ledger_balance,
            'account_number' => $this->account_number,
            'nuban' => $this->nuban,
            'account_name' => $this->account_name,
            'account_tier' => $this->account_tier,
            'account_type' => $this->account_type,
            'bank_name' => $this->bank_name,
            'currency' => $this->currency,
            'routing_number' => $this->routing_number,
            'sort_code' => $this->sort_code,
            'customer_id' => $this->customer_id,
            'savings_description' => $this->savings_description,
        ];
    }
}


