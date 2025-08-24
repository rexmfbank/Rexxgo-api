<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;

class Event extends Model
{
    protected $fillable = [
        'email_account_id',
        'event_title',
        'event_from_date',
        'event_from_time',
        'event_to_date',
        'event_to_time',
        'event_timezone',
        'event_location',
        'event_description',
        'event_invite_guests',
        'event_notification',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

}
