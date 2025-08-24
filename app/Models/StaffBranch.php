<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffBranch extends Model
{
    use HasFactory;

    protected $guarded = [];
    public function branch()
    {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }
}
