<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'file_hash',
        'row_preview',
        'record_count',
    ];
}
