<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class BorrowersGroupMembers extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'group_id',
        'group_name',
        'borrower_id',
        'borrower_name'
    ];

    
    public function borrower()
    {
        return $this->belongsTo(Borrower::class, 'borrower_id');
    }

    public function group()
    {
        return $this->belongsTo(BorrowersGroup::class, 'group_id', 'id');
    }
}
