<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterestAccrual extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_type',
        'link_id',
        'borrower_id',
        'start_date',
        'interest_per_annum',
        'interest_per_day',
        'daily_accrued_interest',
        'company_id',
        'branch_id',
        'no_of_days',
        'last_accrual_date',
        'status',
    ];
    
}
