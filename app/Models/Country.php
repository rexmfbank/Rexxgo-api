<?php

namespace App\Models;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    public function states()
    {
        return $this->hasMany(Province::class);
    }
}
