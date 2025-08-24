<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'coa_id',
        'from_date',
        'to_date',
        'total_debit',
        'total_credit',
        'status',
        'file_path',
        'title'
    ];
}
