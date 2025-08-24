<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterestAccrualDetails extends Model
{
    use HasFactory;

    protected $table = 'interest_accruals_details';

    protected $fillable = [
        'link_id',
        'link_type',
        'borrower_id',
        'balance',
        'daily_accrued_interest',
        'total_accrued_interest',
        'interest_rate_per_annum',  
        'no_of_days',
        'accrual_date',
        'month',
        'company_id',
        'branch_id',
        'status',
    ];

    public function link()
    {
        return $this->morphTo();
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

   
}
