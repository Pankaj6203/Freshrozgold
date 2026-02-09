<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'float',
    ];

    // ─── Relationships ────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderBy('created_at', 'desc');
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Credit amount to wallet
     */
    public function credit(float $amount): self
    {
        $this->increment('balance', $amount);
        return $this->fresh();
    }

    /**
     * Debit amount from wallet (throws if insufficient)
     */
    public function debit(float $amount): self
    {
        if ($this->balance < $amount) {
            throw new \InvalidArgumentException('Insufficient wallet balance');
        }
        $this->decrement('balance', $amount);
        return $this->fresh();
    }

    /**
     * Check if wallet has sufficient balance
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
