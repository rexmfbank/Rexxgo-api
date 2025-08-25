<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Repayment extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = ["id"];
    protected $table = "repayments";
    static $approved = "approved";
    static $pending = "pending";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function Loan() {
        return $this->hasOne(Loan::class, "id", "loan_id");
    }

    public function bank()
    {
        return $this->belongsTo(BankAccount::class,  'bank_account_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }
}
