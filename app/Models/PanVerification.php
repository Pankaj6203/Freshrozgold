<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobile',
        'pan_number',
        'name',
        'name_on_card',
        'father_name',
        'date_of_birth',
        'status',
        'category',
        'is_verified',
        'verification_id',
        'masked_image_url',
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
