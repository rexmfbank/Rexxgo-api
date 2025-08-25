<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class ExpenseType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'expense_type_operating',
    ];

    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }

}
