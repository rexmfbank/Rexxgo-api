<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedDeposit extends Model
{
    use HasFactory;
    use SoftDeletes;

     protected $fillable = [
        'user_id',
        'amount',
        'interest_rate',
        'duration_months',
        'start_date',
        'maturity_date',
        'status',
        'deposit_description',
        'owner',
        'owner_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
