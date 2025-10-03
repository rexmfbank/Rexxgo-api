<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Traits\BelongsToCompanyAndBranch;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory;
    // use BelongsToCompanyAndBranch; //ensure you cannot delete without the owner
    use SoftDeletes;

    protected $table = 'chart_of_accounts';
    protected $fillable = [
        'name',
        'code',
        'type',
        'group',
        'status',
        'cash_flow_type',
        'branch_ids',
        'company_id',
        'branch_id',
        'bring_forward',
        'debit_balance',
        'credit_balance',
    ];

     //guarded fields

    protected $guarded = ["id"];

     protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function account_type()
    {
        return $this->belongsTo(AccountType::class,  'type', 'id');
    }

    public function c_flow_type()
    {
        return $this->belongsTo(CashFlowType::class,  'cash_flow_type', 'id');
    }
}
