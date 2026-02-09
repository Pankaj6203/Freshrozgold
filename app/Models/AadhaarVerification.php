<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AadhaarVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobile',
        'aadhaar_number',
        'request_id',
        'name',
        'father_name',
        'address',
        'city',
        'state',
        'pincode',
        'date_of_birth',
        'gender',
        'email',
        'phone',
        'masked_aadhaar_image',
        'profile_image',
        'is_verified',
        'verification_id',
        'api_response',
        'verified_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_verified'   => 'boolean',
        'api_response'  => 'array',
        'verified_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'mobile', 'mobile');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
}
