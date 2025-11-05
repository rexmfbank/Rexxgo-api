<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class Treasury extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "treasury";

    
}
