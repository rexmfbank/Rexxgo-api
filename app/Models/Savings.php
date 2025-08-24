<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Savings extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = ['id'];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class,  'borrower_id');
    }
    public function staff()
    {
        return $this->belongsTo(Staff::class,  'staff_id');
    }

    public function savings_product()
    {
        return $this->belongsTo(SavingsProduct::class,  'savings_product_id');
    }

    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class,  'savings_id');
    }

    public function lastTransaction()
    {
       return SavingsTransaction::where("savings_id", $this->id)->orderBy("id", 'desc')->first();
    }

    public function deposit($amount)
    {
        $this->available_balance += $amount;
        $this->ledger_balance += $amount;
        $this->save();
        // optionally log transaction
    }


    //==autogenerate the savings account number sequentially
    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($customer) {
    //         $customer->account_number = self::generateAccountNumber();
    //     });
    // }

    // public static function generateAccountNumber()
    // {
    //     // Get the last customer's account number or start at a base number
    //     $lastAccountNumber = self::max('account_number') ?? 999999999; // Starting base number
    //     return $lastAccountNumber + 1;
    // }
}
