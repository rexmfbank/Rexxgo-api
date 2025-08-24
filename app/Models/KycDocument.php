<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrower_id',
        'document_type',
        'file_path',
        'status',
        'remarks',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * Relationship: belongs to a Borrower (individual KYC).
     */
    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }


    /**
     * Check if document is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
