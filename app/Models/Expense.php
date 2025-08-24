<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;

class Expense extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "expenses";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function bank()
    {
        return $this->belongsTo(BankAccount::class,  'bank_account_id', 'id');
    }

    public function ExpenseType()
    {
        return $this->belongsTo(ExpenseType::class,  'expense_type', 'id');
    }


    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }
}
