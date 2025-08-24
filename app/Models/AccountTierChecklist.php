<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class AccountTierChecklist extends Model
{
    use HasFactory;
    protected $table = 'account_tier_checklists';
    protected $fillable = ['company_id', 'tiers'];

    protected $casts = [
        'tiers' => 'array',
    ];
}
