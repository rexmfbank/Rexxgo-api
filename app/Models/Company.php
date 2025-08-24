<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    public function activeBranch () {
        return $this->hasOne(Branch::class, 'id', 'branch_id');
    }
    public function Branches () {
        return $this->hasMany(Branch::class, 'id', 'home_brand_id');
    }
}
