<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    use HasFactory;

    protected $table = 'custom_fields_value';
    
    protected $fillable = [
        'custom_fields_id',
        'user_id',
        'value',
    ];

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_fields_id');
    }
   

    public function borrower()
    {
        return $this->belongsTo(Borrower::class); // Assuming you have a User model
    }

}
