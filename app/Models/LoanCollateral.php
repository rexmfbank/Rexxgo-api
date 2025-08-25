<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class LoanCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'collateral_type_id',
        'collateral_product_name',
        'collateral_add_date',
        'collateral_present_value',
        'collateral_status',
        'collateral_status_date',
        'collateral_loan_id',
        'collateral_serial_number',
        'collateral_model_name',
        'collateral_model_number',
        'collateral_colour',
        'collateral_date_of_man',
        'collateral_condition',
        'collateral_address',
        'collateral_description',
        'collateral_registration_number',
        'collateral_mileage',
        'collateral_engine_number',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    // protected $casts = [
    //     'collateral_loan_id' => 'array', // since this can be multiple, storing as array
    //     'collateral_add_date' => 'date',
    //     'collateral_status_date' => 'date',
    //     'collateral_date_of_man' => 'date',
    // ];

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'collateral_loan_id');
    }

    // Add an accessor for the borrower_name
    public function getBorrowerNameAttribute()
    {
        // dd($this->loan);
        return $this->loan && null!==$this->loan->borrower ? $this->loan->borrower->first_name : 'N/A';
    }
}
