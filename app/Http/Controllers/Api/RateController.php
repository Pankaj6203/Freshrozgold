<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RateController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Get live gold/silver rates
     * GET /api/rates/live
     */
    public function live()
    {
        $response = $this->augmont->getRates();

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch rates'], 503);
        }

        // Normalize Augmont raw response to a clean structure for mobile
        $raw = $response['data'];
        $augmontRates = $raw['result']['data']['rates'] ?? null;
        $blockId      = $raw['result']['data']['blockId'] ?? null;

        if ($augmontRates) {
            $normalized = [
                'gold' => [
                    'buy_rate'  => (float) ($augmontRates['gBuy'] ?? 0),
                    'sell_rate' => (float) ($augmontRates['gSell'] ?? 0),
                    'gst'       => (float) ($augmontRates['gBuyGst'] ?? 0),
                ],
                'silver' => [
                    'buy_rate'  => (float) ($augmontRates['sBuy'] ?? 0),
                    'sell_rate' => (float) ($augmontRates['sSell'] ?? 0),
                    'gst'       => (float) ($augmontRates['sBuyGst'] ?? 0),
                ],
                'block_id' => $blockId,
            ];
        } else {
            // Fallback: return raw data if structure unexpected
            $normalized = $raw;
        }

        return response()->json([
            'success' => true,
            'message' => 'Live rates retrieved',
            'data'    => ['rates' => $normalized, 'source' => 'augmont'],
        ]);
    }

    /**
     * Get historical rates
     * GET /api/rates/historical
     * Supports both Augmont rolling-data API and local DB fallback
     * Core PHP: rates/historical.php used Augmont /rolling-data endpoint
     */
    public function historical(Request $request)
    {
        $fromDate  = $request->query('fromDate');
        $toDate    = $request->query('toDate');
        $metalType = $request->query('metalType', 'gold');

        // If date params provided, try Augmont rolling-data API (matching core PHP)
        if ($fromDate && $toDate) {
            $params = array_filter([
                'fromDate'  => $fromDate,
                'toDate'    => $toDate,
                'metalType' => $metalType,
            ]);

            $response = $this->augmont->getHistoricalRates($params);

            if ($response['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Historical rates from Augmont',
                    'data'    => ['rates' => $response['data'], 'source' => 'augmont'],
                ]);
            }
        }

        // Fallback: local DB rates
        $days  = (int) $request->query('days', 30);
        $rates = \App\Models\GoldRate::where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->get(['buy_rate', 'sell_rate', 'source', 'block_id', 'created_at']);

        return response()->json([
            'success' => true,
            'message' => 'Historical rates retrieved',
            'data'    => ['rates' => $rates, 'source' => 'local'],
        ]);
    }
}
