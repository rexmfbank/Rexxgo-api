<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessApplication extends Model
{
    // use HasFactory, SoftDeletes;
    use HasFactory;

     protected $fillable = ['borrower_id',
        'state', 'lga', 'business_name', 'cac_regno', 
        'date_of_incorporation', 'nature_of_business', 'industry', 'business_address', 
        'website', 'tax_id', 'business_type', 
        'status',  
        'terminal_type', 'merchant_id', 'bank_name', 'bank_code', 'bank_account_name', 'bank_account_no', 'terminal_id', 'device_id',
        'years_in_business',
        'years_at_office_location'
    ];

    protected $dates = ['deleted_at'];

    public function directors()
    {
        return $this->hasMany(Director::class);
    }
}
