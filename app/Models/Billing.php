<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    use HasFactory;

     protected $fillable = [
        'user_id',
        'plan_id',
        'expiry_date',
        'amount_paid',
        'payment_status',
      'payment_method',
      'card_last4',
      'card_expiry_month',
      'card_expiry_year',
      'authorization_code',
      'reference',
      'card_type'
     ];


   public function plan()
   {
      return $this->belongsTo(Plan::class,  'plan_id');
   }

}
