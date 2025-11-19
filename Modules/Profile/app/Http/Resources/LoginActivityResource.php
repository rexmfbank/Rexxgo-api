<?php

namespace Modules\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoginActivityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'email'       => $this->email,
            'ip_address'  => $this->ip_address,
            'device'      => $this->device,
            'logged_at'   => $this->created_at->toDateTimeString(),
        ];
    }
}
