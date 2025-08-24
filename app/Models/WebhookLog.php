<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',   
        'source',
        'payload',
        'ip_address',
        'event_type'
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
