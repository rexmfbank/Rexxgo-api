<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;

class Tax extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "taxes";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
}
