<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'sip_plan_id',
        'user_id',
        'amount_inr',
        'grams',
        'rate_per_gram',
        'status',
        'augmont_txn_id',
        'investment_date',
    ];

    protected $casts = [
        'amount_inr'     => 'decimal:2',
        'grams'          => 'decimal:6',
        'rate_per_gram'  => 'decimal:2',
        'investment_date' => 'date',
    ];

    public function sipPlan()
    {
        return $this->belongsTo(SipPlan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
