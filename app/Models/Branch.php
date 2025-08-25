<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory;
    use SoftDeletes;

   protected $guarded = [];

    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }

    public function loanProducts()
    {
        return $this->hasMany(LoanProduct::class, 'branch_id'); // 'branch_id' is the foreign key
    }

}
