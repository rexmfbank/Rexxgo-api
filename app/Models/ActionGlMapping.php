<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionGlMapping extends Model
{
    use HasFactory;

    protected $fillable = ['action', 'debit_gl_id', 'credit_gl_id'];

    public function debitGl()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_gl_id');
    }

    public function creditGl()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_gl_id');
    }
}
