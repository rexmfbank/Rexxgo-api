<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class SavingsStatus extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
}
