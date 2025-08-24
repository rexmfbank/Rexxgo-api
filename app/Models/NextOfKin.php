<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    use HasFactory;
    protected $table = 'next_of_kins';

    protected $fillable = [
        'company_id',
        'branch_id',
        'borrower_id',
        'first_name',
        'middle_name',
        'last_name',
        'dob',
        'relationship',
        'phone',
        'email',
        'address',
    ];

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

   
}
