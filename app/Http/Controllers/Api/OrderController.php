<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Create physical product order (redeem gold for delivery)
     * POST /api/augmont/orders
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uniqueId'                 => 'required|string',
            'merchantTransactionId'    => 'required|string|min:2|max:100',
            'userAddressId'            => 'required|string',
            'products'                 => 'required|array|min:1',
            'products.*.sku'           => 'required|string',
            'products.*.quantity'      => 'required|integer|min:1|max:999',
            'modeOfPayment'            => 'nullable|string|max:20',
            'lockPrice'                => 'nullable|numeric|min:0',
            'blockId'                  => 'nullable|string',
            'metalType'                => 'nullable|in:gold,silver',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->createOrder($request->only([
            'uniqueId', 'merchantTransactionId', 'userAddressId',
            'products', 'modeOfPayment', 'lockPrice', 'blockId', 'metalType',
        ]));

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to create order'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * List orders
     * GET /api/augmont/orders/{uniqueId}
     */
    public function index(string $uniqueId)
    {
        $response = $this->augmont->listOrders($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch orders'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved',
            'data'    => ['orders' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get order info/detail
     * GET /api/augmont/orders/{uniqueId}/{orderId}
     */
    public function show(string $uniqueId, string $orderId)
    {
        $response = $this->augmont->getOrderInfo($uniqueId, $orderId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch order info'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order info retrieved',
            'data'    => ['order' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
