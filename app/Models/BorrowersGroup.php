<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class BorrowersGroup extends Model
{
    use HasFactory;
    protected $table = 'borrowers_group';

    protected $fillable = [
        'group_name',
        'group_leader',
        'loan_officer',
        'collector_name',
        'meeting_schedule',
        'description'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


}
