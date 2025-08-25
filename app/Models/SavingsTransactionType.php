<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class SavingsTransactionType extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

     protected $fillable = [
        'category',
        'name',
     ];

    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }

}
