<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// use App\Models\Scopes\CompanyScope;

class Staff extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = "staffs";
    protected $guarded = [];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

   public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

   public function hasPermission($permissionName)
    {
        // dd($this->role->permissions, $permissionName, json_decode($this->role->permissions ?? '[]', true));
        return in_array($permissionName, json_decode($this->role->permissions ?? '[]', true));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class,  'staff_id');
    }

    public function savings()
    {
        return $this->hasOne(Savings::class,  'staff_id');
    }

    public function name()
    {
        if ($this->first_name && $this->last_name) return $this->first_name . ' ' . $this->last_name;
        else return 'N/A';
    }
}
