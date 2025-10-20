<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Borrower extends Authenticatable implements JWTSubject
{
    use Notifiable;

    use SoftDeletes;

    protected $table = 'borrowers';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    use HasFactory;
    
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'business_name',
        'unique_number',
        'gender',
        'title',
        'phone',
        'email',
        'dob',
        'address',
        'city',
        'state',
        'zipcode',
        'country',
        'working_status',
        'credit_score',
        'photo',
        'description',

        'customer_type',
        'id_type',
        'id_value',
        'password',          // hashed
        'status',            // e.g. 'pending_otp','active','blocked'
        'phone_verified_at',
        'kyc_status',        // kyc_pending, kyc_verified, kyc_failed

        'loan_officer_id',
        'landline',
        'company_id',
        'branch_id'
    ];

    protected $hidden = [
        'password',
        'pin'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return custom claims for JWT
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $casts = [
        'phone_verified_at' => 'datetime',
    ];

    // protected static function booted()
    // {
    //     static::addGlobalScope(new CompanyScope);
    // }

    public function company()
    {
        return $this->belongsTo(Company::class,  'company_id');
    }

    public function Loans() {
        return $this->hasMany(Loan::class);
    }
   

    public function OpenLoans()
    {
        return $this->hasMany(Loan::class)->where("loan_status", "21")->orWhere('loan_status','20');
    }

    public function getOpenLoansAvailableBalanceSumAttribute()
    {
        return $this->OpenLoans()->sum('principal_amount');
    }

    public function CurrentLoanAmountRequested()
    {
        // Retrieve the first open loan's amount_requested, or return null if none
        return $this->OpenLoans->first()?->amount_requested ?? null;
    }

    public function Officer()
    {
        return $this->belongsTo(Staff::class, 'loan_officer_id');
    }

    public function savings()
    {
        return $this->hasOne(Savings::class,  'borrower_id');
    }

    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class,  'borrower_id');
    }

    public function name()
    {
        if($this->first_name && $this->last_name) return $this->first_name . ' ' . $this->last_name;
        else return 'N/A';
    }

    public function groupMembers()
    {
        return $this->hasOne(BorrowersGroupMembers::class, 'borrower_id', 'id');
    }

    public function GroupName()
    {
        return $this->groupMembers ? $this->groupMembers->group->group_name : null;
    }

    public function GroupId()
    {
        return $this->groupMembers ? $this->groupMembers->group_id : null;
    }

    //get custom values for this borrower
    public function customValues()
    {
        return $this->hasMany(CustomFieldValue::class, 'borrower_id', 'id');
    }

    public function nextOfKin()
    {
        return $this->hasOne(NextOfKin::class);
    }


    // ///-------Autogenerate unique numbers for borrowers sequentially 
    // protected static function boot()
    // {
    //     // parent::boot();

    //     // static::creating(function ($borrower) {
    //     //     $borrower->unique_number = self::generateBorrowerNumber();
    //     // });
    // }

    // public static function generateBorrowerNumber()
    // {
    //     // Get the last borrower's number or start at a base number
    //     $lastBorrowerNumber = self::withoutGlobalScope(CompanyScope::class)->max('unique_number') ?? '1000000'; // Starting base number

    //     // Extract the numeric part using a regular expression
    //     preg_match('/(\D+)(\d+)$/', $lastBorrowerNumber, $matches);

    //     // If a match is found, increment the numeric part
    //     if (isset($matches[2])) {
    //         // Get the non-numeric prefix
    //         $prefix = $matches[1];

    //         // Convert the numeric part to an integer, increment it, and format it back to the original length
    //         $nextNumber = str_pad((int)$matches[2] + 1, strlen($matches[2]), '0', STR_PAD_LEFT);

    //         // Return the new unique number
    //         return $prefix . $nextNumber;
    //     }

    //     // If no match is found, simply increment the last borrower number normally
    //     return (string)((int)$lastBorrowerNumber + 1);
    // }
}
