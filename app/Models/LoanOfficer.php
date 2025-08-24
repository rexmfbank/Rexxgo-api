<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;

class LoanOfficer extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $table = "loan_officers";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
    
    public function Loans() {
        return $this->hasMany(Loan::class);
    }
    public function OpenLoans() {
        return $this->hasMany(Loan::class)->where("loan_status_name", "open");
    }
    public function name()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
