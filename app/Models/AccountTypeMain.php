<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTypeMain extends Model
{
    use HasFactory;

    protected $guarded = ["id"];


    public function account_types()
    {
        return $this->hasMany(AccountType::class,  'parent_id');
    }

    public function coas()
    {
        return $this->hasMany(ChartOfAccount::class,  'group');
    }
}
