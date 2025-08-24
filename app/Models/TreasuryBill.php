<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryBill extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'owner',
        'owner_id',
        'amount',
        'interest_rate',
        'duration_months',
        'start_date',
        'maturity_date',
        'description',
        'recurring',
        'bank_gl'
    ];

    protected $casts = [
        'start_date' => 'date',
        'maturity_date' => 'date',
        'recurring' => 'boolean',
    ];

    public function ownership()
    {
        return $this->belongsTo(Borrower::class, 'owner_id');
    }
}
