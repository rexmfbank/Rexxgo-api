<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;


class Journal extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = ["id"];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

    
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class,  'coa_id');
    }
}
