<?php

namespace Modules\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoginActivityResource extends JsonResource
{
    public function toArray($request)
    {
        $borrower = auth()->guard('borrower')->user();
        $timezone = $borrower->timezone;
        return [
            'id'          => $this->id,
            'email'       => $this->email,
            'ip_address'  => $this->ip_address,
            'device'      => $this->device,
            'country'      => $this->country,
            'logged_at'   => $this->created_at->copy()->timezone($timezone)->toDateTimeString(),
        ];
    }
}
