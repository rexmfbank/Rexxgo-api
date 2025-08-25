<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class InterBankTransfer extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "interbank_transfers";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function from_bank()
    {
        return $this->belongsTo(BankAccount::class,  'from_bank_account_id');
    }
    public function to_bank()
    {
        return $this->belongsTo(BankAccount::class,  'to_bank_account_id');
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class,  'branch_id');
    }
}
