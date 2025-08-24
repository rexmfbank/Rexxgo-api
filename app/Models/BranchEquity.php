<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchEquity extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "branch_equity";


    public function bank()
    {
        return $this->belongsTo(BankAccount::class,  'bank_account_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class,  'branch_id');
    }
}
