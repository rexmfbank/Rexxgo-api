<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;

class BankAccount extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "bank_accounts";

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function getBranchesAttribute()
    {
        // Get the branch IDs as an array
        $branchIds = explode(',', $this->branches_id);

        // Return a collection of Branch models
        return Branch::whereIn('id', $branchIds)->get();
    }
}
