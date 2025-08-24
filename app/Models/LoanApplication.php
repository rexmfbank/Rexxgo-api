<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;


class LoanApplication extends Model
{
    use HasFactory;

    use SoftDeletes; // Enable soft delete

    protected $dates = ['deleted_at']; // Ensure deleted_at is handled as a date

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'dob',
        'phone',
        'other_phone',
        'bvn',
        'nin',
        'email',
        'gender',
        'address',
        'state',
        'employment_type',
        'employer',
        'ippis',
        'employment_date',
        'grade_level',
        'net_pay',
        'tenor',
        'loan_amount',
        'purpose',
        'customer_status',
        'photo',
        'id_card',
        'payslip',
        'bank_statement',
        'account_officer_code',
        'officer_email',
        'feedback',
        'agreement',
        'bank_name',
        'genda',
        'command',
        'next_of_kin_name',
        'next_of_kin_phone',
        'account_name',
        'loan_form',
        'status',
        'other_files',
        'account_number',
        'customer_details',
        'credit_approval',
        'audit_approval',
        'manager_approval',
        'loan_product',
        'officer_name',
        'in_charge',
        'rejection_reason', 
        'approved_amount', 
        'approved_tenor', 
        'branch_id', 
        'company_id', 
        'loan_officer_id', 
        'loan_status', 
        'loan_title',
        'borrower_id',
        'savings_id',
        'loan_id',
        'savings_product_id',
        'savings_account_no',
        'date_approved',
        'in_charge_time',
        'pay_off', // New field for pay off amount
        'withdrawal', // New field for withdrawal amount
        'first_repayment_date'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    public function creditScore()
    {
        return $this->hasOne(CreditScore::class);
    }
}
