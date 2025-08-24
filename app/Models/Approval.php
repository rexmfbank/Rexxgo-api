<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'user_id',
        'staff_name',
        'remark',
        'datetime',
        'in_charge',
        'tenor',
        'amount',
        'status',
    ];

    /**
     * Get the user who made the approval request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the staff member in charge of the approval.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
