<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestorAccountTransaction extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

        public function bank()
    {
        return $this->belongsTo(BankAccount::class,  'bank_account_id', 'id');
    }
}
