<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class LoanStatus extends Model
{
    use HasFactory;

    protected $table = 'loan_status';

    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }


    public function childrenStatuses()
    {
        return $this->hasMany(LoanStatus::class, 'children', 'name');
    }
}
