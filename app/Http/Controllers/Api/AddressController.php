<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Create user address (Augmont)
     * POST /api/augmont/addresses
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uniqueId'  => 'required|string',
            'name'      => 'required|string|min:2|max:100',
            'address'   => 'required|string|min:5|max:500',
            'pincode'   => 'required|digits:6',
            'city'      => 'required|string',
            'state'     => 'required|string',
            'mobile'    => 'nullable|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->createUserAddress($request->uniqueId, $request->only([
            'name', 'address', 'pincode', 'city', 'state', 'mobile',
        ]));

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to add address'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address added successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * List user addresses (Augmont)
     * GET /api/augmont/addresses/{uniqueId}
     */
    public function index(string $uniqueId)
    {
        $response = $this->augmont->getUserAddresses($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch addresses'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Addresses retrieved',
            'data'    => ['addresses' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Delete user address (Augmont)
     * DELETE /api/augmont/addresses/{uniqueId}/{addressId}
     */
    public function destroy(string $uniqueId, string $addressId)
    {
        $response = $this->augmont->deleteUserAddress($uniqueId, $addressId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to delete address'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully',
        ]);
    }
}
