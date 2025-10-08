<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'savings_products';
    protected $guarded = ['id'];
    static $usd = "USD";
    static $ngn = "NGN";
    static $usdc = "USDC";

    protected $fillable = [
        'allow_savings_overdrawn',
        'overdraft_interest_rate',
        'overdraft_limit',
        "company_id",
        "branch_id",
        "product_name",
        "allow_duplicate_transactions",
        "interest_rate_per_annum",
        "interest_method",
        "interest_posting_frequency",
        "when_should_interest_be_added",
        "minimum_balance_for_interest_rate",
        "minimum_balance_for_withdrawal",
        "branch_ids"
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function savings()
    {
        return $this->hasMany(Savings::class,  'savings_product_id');
    }

    public function branches()
    {
        $branchIds = explode(',', $this->branch_ids);
        return Branch::whereIn('id', $branchIds)->get();
    }
}
