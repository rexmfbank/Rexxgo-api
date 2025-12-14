<?php

namespace Modules\Wallet\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        $borrower = auth()->guard('borrower')->user();
        $timezone = $borrower->timezone ?? config('app.timezone');

        $createdAt = $this->created_at
            ->copy()
            ->timezone($timezone);

        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'borrower_id'       => $this->borrower_id,
            'savings_id'        => $this->savings_id,
            'amount'            => (float) $this->transaction_amount,
            'balance'           => (float) $this->balance,
            'currency'          => $this->currency,
            'wallet_currency'   => $this->savings ? $this->savings->currency : '',
            'type'              => $this->transaction_type,
            'description'       => $this->transaction_description,
            'status'            => $this->status_id,
            'provider'          => $this->provider,
            'external_tx_id'    => $this->external_tx_id,
            'external_response' => json_decode($this->external_response, true),

            // ✅ Timezone-based values
            'date' => $createdAt->toDateString(),        // e.g. 2025-12-08
            'time' => $createdAt->format('H:i:s'),       // e.g. 14:35:22

            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $this->updated_at
                                ? $this->updated_at->copy()->timezone($timezone)->toDateTimeString()
                                : null,

            'category'      => $this->category,
            'details'       => json_decode($this->details, true),
            'currency_pair' => $this->currency_pair,
        ];
    }
}
