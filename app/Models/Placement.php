<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Placement extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'bank_gl',
        'placement_gl',
        'description',
        'status'
    ];

    public function bankGlAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'bank_gl');
    }

    public function placementGlAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'placement_gl');
    }
}
