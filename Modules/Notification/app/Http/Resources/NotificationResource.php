<?php

namespace Modules\Notification\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'data' => json_decode($this->data, true),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at?->toDateTimeString(),
            'borrower_id' => $this->borrower_id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
        ];
    }
}
