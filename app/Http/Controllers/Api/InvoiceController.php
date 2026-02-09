<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;

class InvoiceController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Get buy invoice
     * GET /api/augmont/invoices/buy/{transactionId}
     */
    public function buy(string $transactionId)
    {
        $response = $this->augmont->getBuyInvoice($transactionId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch buy invoice'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Buy invoice retrieved',
            'data'    => ['invoice' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get sell invoice
     * GET /api/augmont/invoices/sell/{transactionId}
     */
    public function sell(string $transactionId)
    {
        $response = $this->augmont->getSellInvoice($transactionId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch sell invoice'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sell invoice retrieved',
            'data'    => ['invoice' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get redeem (physical delivery) invoice
     * GET /api/augmont/invoices/redeem/{transactionId}
     */
    public function redeem(string $transactionId)
    {
        $response = $this->augmont->getRedeemInvoice($transactionId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch redeem invoice'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Redeem invoice retrieved',
            'data'    => ['invoice' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
