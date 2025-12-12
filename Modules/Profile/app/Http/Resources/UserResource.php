<?php

namespace Modules\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'business_name' => $this->business_name,
            'unique_number' => $this->unique_number,
            'gender' => $this->gender,
            'title' => $this->title,
            'phone' => $this->phone,
            'email' => $this->email,
            'dob' => $this->dob,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'landline' => $this->landline,
            'country' => $this->country,
            'working_status' => $this->working_status,
            'customer_status' => $this->customer_status,
            'credit_score' => $this->credit_score,

            'photo' => $this->photo ? url("storage/".$this->photo) : null,
            'avatar' => $this->photo ? url("storage/".$this->photo) : null,

            'description' => $this->description,
            'id_type' => $this->id_type,
            'id_value' => $this->id_value,
            'bvn' => $this->bvn,
            'passport_photo' => $this->passport_photo,
            'nin' => $this->nin,
            'nin_slip_image' => $this->nin_slip_image,
            'sign' => $this->sign,
            'biometrics' => $this->biometrics,

            'customer_type' => $this->customer_type,
            'status' => $this->status,
            'kyc_tier' => $this->kyc_tier,
            'phone_verified_at' => $this->phone_verified_at,
            'otp' => $this->otp,
            'otp_expires_at' => $this->otp_expires_at,
            'facial_verification_id' => $this->facial_verification_id,
            'kyc_status' => $this->kyc_status,

            'loan_officer_id' => $this->loan_officer_id,
            'branch_id' => $this->branch_id,
            'company_id' => $this->company_id,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            'kyc_link' => $this->kyc_link,
            'tos_link' => $this->tos_link,
            'tos_status' => $this->tos_status,
            'bridge_customer_id' => $this->bridge_customer_id,
            'rejection_reasons' => $this->rejection_reasons,

            'wallet_created' => $this->wallet_created,
            'pin_created' => $this->pin_created,
            'passcode_created' => $this->passcode_created,
            'biometrics_created' => $this->biometrics_created,

            'fcm_token' => $this->fcm_token,

            // NEW FIELDS YOU ADDED
            'show_account_balance' => (bool) $this->show_account_balance,
            'allow_notification' => (bool) $this->allow_notification,
            'updates_notification' => (bool) $this->updates_notification,
            'balance_changes_notification' => (bool) $this->balance_changes_notification,
        ];
    }
}
