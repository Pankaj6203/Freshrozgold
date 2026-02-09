<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoldRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'buy_rate',
        'sell_rate',
        'source',
        'block_id'
    ];

    protected $casts = [
        'buy_rate' => 'decimal:2',
        'sell_rate' => 'decimal:2',
    ];

    /**
     * Get the latest gold rate
     */
    public static function getLatest()
    {
        return self::latest()->first();
    }

    /**
     * Get historical rates for the last N days
     */
    public static function getHistorical($days = 7)
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
