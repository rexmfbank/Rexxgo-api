<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanFeesValue extends Model
{
   use HasFactory;

    // Specify the table name if it's different from the default (plural form)
    protected $table = 'loan_fees_values';

    // Define the fillable fields for mass assignment
    protected $fillable = [
        'loan_id',
        'loan_fees_id',
        'value',
        'loan_product_id'
    ];

    /**
     * Relationship to Loan (assuming 'loan_id' is a foreign key to the 'loans' table).
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Relationship to LoanFee (assuming 'loan_fees_id' is a foreign key to the 'loan_fees' table).
     */
    public function loanFee()
    {
        return $this->belongsTo(LoanFee::class, 'loan_fees_id');
    }
}
