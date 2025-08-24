<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'loan_product_name',
        'set_release_date',
        'disbursed_by',
        'minimum_prinicpal_amount',
        'default_principal_amount',
        'maximum_principal_amount',
        'interest_method',
        'interest_type',
        'loan_interest_period',
        'minimum_loan_interest',
        'default_loan_interest',
        'maximum_loan_interest',
        'loan_duration_period',
        'minimum_loan_duration',
        'default_loan_duration',
        'maximum_loan_duration',
        'repayment_cycle',
        'minimum_number_of_repayments',
        'default_number_of_repayments',
        'max_no_of_repayments',
        'loan_status',
        'branch_id',
        'decimal_places',
        'repayment_order',
        'add_automatic_payments',
        'extend_loan_after_maturity',
        'calculate_interest_on',
        'loan_interest_rate_after_maturity',
        'recurring_period_after_maturity',
        'first_repayment_amount',
        'last_repayment_amount',
        'override_each_repayment_amount_to',
        'calculate_interest_pro_rata_basis',
        'interest_charged_in_loan_schedule',
        'principal_charged_in_loan_schedule',
        'balloon_repayment_amount',
        'move_first_repayment_date',
        'loan_schedule_description',
        'source_of_fund_for_principal_amount',
        'stop_duplicate_repayment',
        'round_up_off_interest',
        'time_to_post_between',
        'cashorbank',
        'extend_loan_maturity_interest_type',
        'include_fees_after_maturity',
        'keep_loan_status_as_past_maturity'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
    
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id'); // 'branch_id' is the foreign key
    }

    public function Loans() {
        return $this->hasMany(Loan::class);
    }
    public function OpenLoans() {
        return $this->hasMany(Loan::class)->where("loan_status", "1");
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class, 'loan_product');
    }
}
