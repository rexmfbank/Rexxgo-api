<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    public function group()
    {
        return $this->belongsTo(AccountTypeMain::class,  'parent_id', 'id');
    }
}
