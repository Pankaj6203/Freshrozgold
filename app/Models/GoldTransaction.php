<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoldTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unique_id',
        'transaction_id',
        'merchant_transaction_id',
        'invoice_id',
        'invoice_number',
        'transaction_type',
        'metal_type',
        'quantity',
        'rate',
        'amount',
        'tax_amount',
        'total_amount',
        'block_id',
        'customer_name',
        'customer_email',
        'customer_mobile',
        'payment_method',
        'mode_of_payment',
        'payment_status',
        'payment_reference',
        'invoice_url',
        'status',
        'source',
        'invoice_data',
        'augmont_response',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_data' => 'array',
    ];

    /**
     * Scope for buy transactions
     */
    public function scopeBuy($query)
    {
        return $query->where('transaction_type', 'buy');
    }

    /**
     * Scope for sell transactions
     */
    public function scopeSell($query)
    {
        return $query->where('transaction_type', 'sell');
    }

    /**
     * Check if transaction is completed
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }
}
