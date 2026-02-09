<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Get withdrawal info (gold balance, sell limits, etc.)
     * GET /api/augmont/withdraw/{uniqueId}
     */
    public function info(Request $request, string $uniqueId)
    {
        $sellTxnId = $request->query('sellTxnId', '');
        if (!$sellTxnId) {
            return response()->json(['success' => false, 'message' => 'sellTxnId query parameter is required'], 400);
        }
        $response = $this->augmont->getWithdrawInfo($sellTxnId, $uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch withdraw info'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdraw info retrieved',
            'data'    => ['withdraw' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Update withdrawal bank details (retry failed withdrawal)
     * PUT /api/augmont/withdraw
     * Matches core PHP: withdraw/update.php — updates bank for failed withdrawal
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uniqueId'      => 'required|string',
            'sellTxnId'     => 'required|string',
            'accountName'   => 'required|string|min:2|max:50|regex:/^[a-zA-Z\s]+$/',
            'accountNumber' => 'required|string|min:9|max:18|regex:/^[0-9]+$/',
            'ifscCode'      => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->updateWithdraw(
            $request->input('sellTxnId'),
            $request->input('uniqueId'),
            $request->only(['accountName', 'accountNumber', 'ifscCode'])
        );

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to update withdrawal bank details'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal bank details updated successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * Get gold/silver balance
     * GET /api/augmont/balance/{uniqueId}
     */
    public function balance(string $uniqueId)
    {
        $response = $this->augmont->getBalance($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch balance'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Balance retrieved',
            'data'    => ['balance' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
