<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'public_key',
        'secret_key',
        'webhook_url',
        'is_active',
        'extra_config'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'extra_config' => 'array',
    ];
}
