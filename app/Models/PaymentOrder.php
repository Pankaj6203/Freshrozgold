<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'cf_order_id',
        'order_amount',
        'order_currency',
        'order_status',
        'payment_session_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'order_note',
        'payment_details',
        'api_response',
        'paid_at',
    ];

    protected $casts = [
        'order_amount' => 'decimal:2',
        'payment_details' => 'array',
        'api_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Scope for active orders
     */
    public function scopeActive($query)
    {
        return $query->where('order_status', 'ACTIVE');
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('order_status', 'PAID');
    }

    /**
     * Check if order is paid
     */
    public function isPaid()
    {
        return $this->order_status === 'PAID';
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid($paymentDetails = null)
    {
        $this->update([
            'order_status' => 'PAID',
            'paid_at' => now(),
            'payment_details' => $paymentDetails,
        ]);
    }
}
