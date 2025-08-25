<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class Income extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "incomes";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function bank()
    {
        return $this->belongsTo(BankAccount::class,  'bank_account_id', 'id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }
}
