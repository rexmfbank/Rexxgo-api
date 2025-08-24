<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'credit_score',
        'risk_level',
        'rule_breakdown',
        'recommendation',
        'metadata',
    ];

    protected $casts = [
        'rule_breakdown' => 'array',
        'metadata' => 'array',
        'credit_score' => 'decimal:2',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }
    
}
