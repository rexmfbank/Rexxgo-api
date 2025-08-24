<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Director extends Model
{
    use HasFactory;

    protected $table = 'business_directors';

    protected $fillable = [
        'business_application_id', 'first_name', 'last_name', 'middle_name', 'dob', 
        'address', 'bvn', 'gender', 'nationality', 'phone','email', 'shares_held', 'valid_id_type',
        'valid_id_file'
    ];

    public function businessApplication()
    {
        return $this->belongsTo(BusinessApplication::class);
    }
}
