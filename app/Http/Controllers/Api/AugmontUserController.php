<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use App\Http\Requests\CreateAugmontUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AugmontUserController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Create Augmont user account
     * POST /api/augmont/users
     */
    public function store(CreateAugmontUserRequest $request)
    {
        $response = $this->augmont->createUser($request->validated());

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to create user'], 503);
        }

        // Link authenticated user to Augmont account
        if (Auth::check()) {
            $user = Auth::user();
            $user->update([
                'augmont_user_id' => $request->uniqueId
            ]);
            Log::info('Augmont user linked to local user', [
                'local_user_id' => $user->id,
                'augmont_user_id' => $request->uniqueId
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User account created successfully',
            'data'    => ['user_account' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Update Augmont user account
     * PUT /api/augmont/users/{uniqueId}
     */
    public function update(Request $request, string $uniqueId)
    {
        $validator = Validator::make($request->all(), [
            'userName'         => 'nullable|string|min:2|max:50',
            'mobileNumber'     => 'nullable|digits:10',
            'emailId'          => 'nullable|email|max:50',
            'userCity'         => 'nullable|string',
            'userState'        => 'nullable|string',
            'userPincode'      => 'nullable|digits:6',
            'dateOfBirth'      => 'nullable|date_format:Y-m-d',
            'nomineeName'      => 'nullable|string|max:50',
            'nomineeDateOfBirth' => 'nullable|date_format:Y-m-d|before:-18 years',
            'nomineeRelation'  => 'nullable|string|min:2|max:25',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->updateUser($uniqueId, $request->all());

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to update user'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'User account updated successfully',
            'data'    => ['user_account' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get Augmont user passbook
     * GET /api/augmont/users/{uniqueId}/passbook
     */
    public function passbook(string $uniqueId)
    {
        $response = $this->augmont->getUserPassbook($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch passbook'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Passbook retrieved successfully',
            'data'    => ['passbook' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
