<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobiles',
        'otp',
        'expires_at',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'expires_at'  => 'datetime',
    ];

    /**
     * Scope: only valid (not expired, not verified) OTPs
     */
    public function scopeValid($query)
    {
        return $query->where('is_verified', false)
                     ->where('expires_at', '>', now());
    }

    /**
     * Scope: filter by mobile
     */
    public function scopeForMobile($query, string $mobile)
    {
        return $query->where('mobiles', $mobile);
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Mark OTP as verified
     */
    public function markVerified(): bool
    {
        return $this->update(['is_verified' => true]);
    }
}
