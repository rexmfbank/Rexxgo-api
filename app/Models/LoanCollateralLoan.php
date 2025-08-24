<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanCollateralLoan extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function Collateral() {
        return $this->belongsTo(LoanCollateral::class);
    }
}
