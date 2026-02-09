<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'unique_id',
        'pan',
        'aadhaar',
        'kyc_status',
        'kyc_verified_at',
        'augmont_user_id',
        'password',
    ];

    // ─── Relationships ────────────────────────────────────────

    public function banks()
    {
        return $this->hasMany(UserBank::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get or create wallet for this user
     */
    public function getOrCreateWallet(): Wallet
    {
        return $this->wallet ?? $this->wallet()->create(['balance' => 0]);
    }

    public function goldTransactions()
    {
        return $this->hasMany(GoldTransaction::class);
    }

    public function sipPlans()
    {
        return $this->hasMany(SipPlan::class);
    }

    public function panVerification()
    {
        return $this->hasOne(PanVerification::class, 'mobile', 'mobile');
    }

    public function aadhaarVerification()
    {
        return $this->hasOne(AadhaarVerification::class, 'mobile', 'mobile');
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
