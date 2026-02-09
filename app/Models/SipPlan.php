<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'frequency',
        'duration_months',
        'start_date',
        'next_investment_date',
        'status',
        'total_investments',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'start_date'           => 'date',
        'next_investment_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(SipTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
