<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanFee extends Model
{
    use HasFactory;

     protected $table = 'loan_fees';

    // Define the fillable fields for mass assignment
    protected $fillable = [
        'name',
        'fee_calculation_type',
        'fee_percentage_of',
        'deductable_fees',
    ];

  

}
