<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanAccountTransaction extends Model
{
    use HasFactory;

    protected $table = 'loan_account_transactions';

    // Define the fillable fields for mass assignment
    protected $fillable = [
        'loan_id',
        'transaction_type',
        'amount',
        'transaction_date',
        'balance_after',
        'reference',
        'gl_debit_coa_id',
        'gl_credit_coa_id',
    ];

    /**
     * Relationship with Loan model
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    /**
     * Relationship with GL Debit Account
     */
    public function glDebitAccount()
    {
        return $this->belongsTo(GLAccount::class, 'gl_debit_coa_id');
    }

    /**
     * Relationship with GL Credit Account
     */
    public function glCreditAccount()
    {
        return $this->belongsTo(GLAccount::class, 'gl_credit_coa_id');
    }
}
