<?php

namespace Modules\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'dob' => $this->dob,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country' => $this->country,
            'gender' => $this->gender,
            'photo' => $this->photo,
            'customer_type' => $this->customer_type,
            'kyc_status' => $this->kyc_status,
            'id_type' => $this->id_type,
            'id_value' => $this->id_value,
        ];
    }
}


