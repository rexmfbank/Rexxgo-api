<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'assets';

    protected $fillable = [
        'asset_type',
        'is_purchased_or_opening_value',
        'date',
        'value',
        'bank_account_id',
        'asset_sold',
        'sold_date',
        'sold_value',
        'destination_sold_bank_account_id',
        'replacement_value',
        'serial_number',
        'bought_from',
        'description_of_asset',
        'invoice_or_receipt_photo',
        'depreciation_value_over_time'
    ];
}
