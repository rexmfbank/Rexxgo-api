<?php

namespace Modules\Wallet\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'borrower_id' => $this->borrower_id,
            'savings_id' => $this->savings_id,
            'amount' => (float) $this->transaction_amount,
            'balance' => (float) $this->balance,
            'currency' => $this->currency,
            'wallet_currency' => $this->savings ? $this->savings->currency : "",
            'type' => $this->transaction_type,
            'description' => $this->transaction_description,
            'status' => $this->status_id,
            'provider' => $this->provider,
            'external_tx_id' => $this->external_tx_id,
            'external_response' => json_decode($this->external_response, true),
            'date' => $this->transaction_date,
            'time' => $this->transaction_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
