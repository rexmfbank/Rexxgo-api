<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = [
        'borrower_id',
        'external_account_id',
        'account_number_last4',
        'routing_number',
        'bank_name',
        'account_owner_name',
        'currency'
    ];
}
