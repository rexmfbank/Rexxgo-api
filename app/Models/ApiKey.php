<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
//include model Borrower
use App\Models\Borrower;

class ApiKey extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'branch_id',
        'public_key',
        'secret_key',
        'type',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::Borrower);
    // }
}


