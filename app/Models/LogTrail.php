<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogTrail extends Model
{
    use HasFactory;

    // Define the table and the fillable fields
    protected $table = 'log_trails'; // Optional if your table follows Laravel's naming convention
    protected $fillable = ['user_id', 'payload', 'url', 'response', 'type', 'status'];

}
