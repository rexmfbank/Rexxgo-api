<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
class AccountGlMapping extends Model
{

    use HasFactory;

    // The name of the table
    protected $table = 'account_gl_mappings';

    // The attributes that are mass assignable
    protected $fillable = [
        'product_type',                    // Example: varchar(255) for product type
        'product_id',                      // Example: bigint for product ID
        'control_gl',                      // Example: bigint for control GL
        'fees_gl',                         // Example: bigint for fees GL
        'principal_overdue_gl',            // Example: bigint for principal overdue GL
        'interest_overdue_gl',             // Example: bigint for interest overdue GL
        'interest_receivable_gl',          // Example: bigint for interest receivable GL
        'fees_in_suspense_gl',             // Example: bigint for fees in suspense GL
        'interest_in_suspense_gl',         // Example: bigint for interest in suspense GL
        'interest_income_gl',              // Example: bigint for interest income GL
        'fees_income_gl',                  // Example: bigint for fees income GL
        'provision_expense_gl',            // Example: bigint for provision expense GL
        'provision_loan_loss_gl',          // Example: bigint for provision loan loss GL
        'interest_expense_gl',             // Example: bigint for interest expense GL
        'interest_payable_gl',             // Example: bigint for interest payable GL
        'overdraft_gl',                    // Example: bigint for overdraft GL
        'overdraft_income_gl',             // Example: bigint for overdraft income GL
        'created_at',                      // Example: timestamp for created_at
        'updated_at',                      // Example: timestamp for updated_at
    ];

    // The attributes that should be hidden for arrays
    protected $hidden = [
        // Fields you want to hide when converting to array or JSON
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }

    // The date attributes if you are using timestamps
    protected $dates = [
        'created_at', // Example of a date field
        'updated_at', // Example of a date field
    ];

}
