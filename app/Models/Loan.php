<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory;
    use SoftDeletes;

    static $completed = "completed";
    protected $fillable = [
        'application_id',
        'borrower_id',
        'principal_amount',
        'loan_release_date',
        'interest_method',
        'interest_type',
        'maturity_date',
        'interest_amount',
        'total_due_amount',
        'balloon_repayment_amount',
        'fees_amount',
        'balance_amount',
        'amount_paid',
        'source_principal_amount',
        'penalty_amount',
        'repayment_cycle',
        'loan_interest_rate',
        'loan_duration',
        'number_of_repayments',
        'amortization_due',
        'loan_status',
        'loan_product_id',
        'decimal_places',
        'interest_start_date',
        'bank_account_type',
        'non_deductible_fees',
        'collateral_status',
        'days_past_due',
        'days_past_maturity',
        'days_to_maturity',
        'deductable_fees',
        'disbursed_by',
        'last_repayment',
        'loan_officer_id',
        'loan_status_name',
        'loan_title',
        'past_due',
        'loan_account_number',
        'accrued_interest',
        'company_id',
        'branch_id',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class, 'borrower_id');
    }


    public function loanStatus()
    {
        return $this->belongsTo(LoanStatus::class, 'loan_status');
    }
  
    // Define the relationship to LoanCollateral model
    public function collaterals()
    {
        return $this->hasMany(LoanCollateral::class, 'collateral_loan_id');
    }


    public function LoanProduct()
    {
        return $this->hasOne(LoanProduct::class, 'id', 'loan_product_id');
    }

    public function LoanStatusHistory() {
        return $this->hasMany(LoanStatusHistory::class, 'loan_id', 'id');
    }
    public function schedules() {
        return $this->hasMany(Schedule::class, 'loan_id', 'id');
    }
    public function repayments() {
        return $this->hasMany(Repayment::class, 'loan_id', 'id');
    }
    public function LastRepayment() {
        return Repayment::where("loan_id", $this->id)->orderBy("id", "DESC")->first();
    }
    public function LastSchedule() {
        return Schedule::where("loan_id", $this->id)->orderBy("id", "DESC")->first();
    }
    public function NextDue() {
        return Schedule::where("loan_id", $this->id)->where("status", "pending")->first();
    }
    public function DueRepayments() {
        return Schedule::where("loan_id", $this->id)->where("repayment_date", "<", date("Y-m-d"))->where("status", "pending")->get();
    }
    public function CompletedSchedules() {
        return Schedule::where("loan_id", $this->id)->where("status", "completed")->get();

    }

    public function PendingSchedules() {
        return $this->hasMany(Schedule::class, 'loan_id', 'id')->where("status", Schedule::$pending);
    }

    public function loan_product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
    
    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }
}
