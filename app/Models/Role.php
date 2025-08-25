<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    public static $owner = "Branch Manager";
    public static $staff = "Staff";
    public static $loanOfficer = "Loan Officer";
    public static $branchManager = "Branch Manager";
    public static $teller = "Teller";
    public static $collector = "Collector";

    public static $roles = ["Admin", "Loan Officer","Sales Department","Sales Department Manager","Credit Department","Operations Department","Operations Manager", "Audit Department","Credit Manager", "Branch Manager", "Teller", "Collector"];
    protected $fillable = ["role_name", "permissions", "company_id", "branch_id"];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }


    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

     public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

}
