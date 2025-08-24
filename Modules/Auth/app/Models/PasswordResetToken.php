<?php

namespace Modules\Auth\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Auth\Database\Factories\PasswordResetTokenFactory;

class PasswordResetToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */

    protected $table = 'password_reset_tokens';
    public $timestamps = false; // we already have created_at
    protected $fillable = ['email', 'token', 'updated_at'];
    protected $primaryKey = 'email'; // <- use email as primary key
    public $incrementing = false; // <- because email is not auto-increment
    protected $keyType = 'string'; // <- email is string
}
