<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class LoanComment extends Model
{
    use HasFactory;
    protected $guarded = [];

    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }


    public function Staff()
    {
        return $this->hasOne(Staff::class, 'id', 'staff_id');
    }
}
