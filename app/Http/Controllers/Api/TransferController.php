<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Create gold transfer
     * POST /api/augmont/transfers
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fromUniqueId' => 'required|string',
            'toUniqueId'   => 'required|string',
            'goldWeight'   => 'required|numeric|min:0.001',
            'metalType'    => 'required|in:gold,silver',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->createTransfer($request->all());

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to create transfer'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Gold transfer initiated successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * List user transfers
     * GET /api/augmont/transfers/{uniqueId}
     */
    public function index(string $uniqueId)
    {
        $response = $this->augmont->listTransfers($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch transfers'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transfers retrieved',
            'data'    => ['transfers' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get transfer info/detail
     * GET /api/augmont/transfers/{uniqueId}/{transferId}
     */
    public function show(string $uniqueId, string $transferId)
    {
        $response = $this->augmont->getTransferInfo($uniqueId, $transferId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch transfer info'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transfer info retrieved',
            'data'    => ['transfer' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
