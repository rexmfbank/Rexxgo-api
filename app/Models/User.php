<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
   

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;
    

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['first_name', 'email', 'business_name', 'last_name', 'reset_token', 'terms', 'verified', 'company_id','onesignal_id'];

    public function company()
    {
        return $this->hasOne(Company::class);
    }
    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

   

    public function currentBranch()
    {
        return Branch::where('id', function ($query) {
            $query->select('active_branch_id')
            ->from('companies')
            ->where('user_id', $this->id)
                ->limit(1);
        })->first();
    }

    //HeadQuarters is the first branch
    public function HQ()
    {
        // Assuming the User has one Company and Branches belong to the Company
        return Branch::where('home_branch_id', $this->company->id)->first();
        // return $this->hasOneThrough(Branch::class, Company::class, 'user_id', 'home_branch_id', 'id', 'id')->first();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


}
