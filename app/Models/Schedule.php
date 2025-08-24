<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = [];
    static $completed = "completed";
    static $pending = "pending";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function repayments() {
        return $this->hasMany(Repayment::class);
    }
    public function TotalPaid() {
        $allRepaymentsForThisSchedule = Repayment::where("schedule_id", $this->id)->sum("total_amount_paid");
        return $allRepaymentsForThisSchedule;
    }
    
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
