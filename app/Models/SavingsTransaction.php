<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ["id"];
    protected $table = "savings_transactions";



    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }
    
    public function bank()
    {
        return $this->belongsTo(BankAccount::class,  'bank_account_id', 'id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }

    public function savings()
    {
        return $this->belongsTo(Savings::class,  'savings_id');
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class,  'borrower_id');
    }

    public function status()
    {
        return $this->belongsTo(SavingsStatus::class,  'status_id');
    }

    public function staff()
    {
        return $this->belongsTo(SavingsStatus::class,  'staff_id');
    }

    public function transactionType()
    {
        return $this->belongsTo(SavingsTransactionType::class,  'transaction_type');
    }
}
