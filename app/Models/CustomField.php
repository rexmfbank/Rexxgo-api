<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class CustomField extends Model
{
    use HasFactory;

    protected $table = 'custom_fields';

    // Fillable attributes
    protected $fillable = [
        'table_name',
        'field_name',
        'data_type',
        'required',
        'branches',
        'default_value',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

}
