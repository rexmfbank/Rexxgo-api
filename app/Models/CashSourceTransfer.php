<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashSourceTransfer extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    public function from_source()
    {
        return $this->belongsTo(CashSource::class,  'cash_source_from_id');
    }
    public function to_source()
    {
        return $this->belongsTo(CashSource::class,  'cash_source_to_id');
    }
}
