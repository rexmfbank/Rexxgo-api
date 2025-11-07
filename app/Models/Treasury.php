<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Treasury extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $table = "treasury";

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Savings::class, 'savings_id', 'id');
    }
    
}
