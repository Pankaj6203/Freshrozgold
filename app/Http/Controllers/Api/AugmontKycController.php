<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AugmontKycController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Get Augmont user KYC status
     * GET /api/augmont/kyc/{uniqueId}
     */
    public function show(string $uniqueId)
    {
        $response = $this->augmont->getUserKyc($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch KYC'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC details retrieved',
            'data'    => ['kyc' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Update Augmont user KYC
     * PUT /api/augmont/kyc/{uniqueId}
     */
    public function update(Request $request, string $uniqueId)
    {
        $validator = Validator::make($request->all(), [
            'panNumber'     => 'nullable|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'panAttachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'dateOfBirth'   => 'nullable|date_format:Y-m-d',
            'nameAsPerPan'  => 'nullable|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $data = $request->only(['panNumber', 'dateOfBirth', 'nameAsPerPan']);

        // Handle file upload
        if ($request->hasFile('panAttachment')) {
            $file = $request->file('panAttachment');
            $data['panAttachment'] = base64_encode(file_get_contents($file->getRealPath()));
            $data['panAttachmentType'] = $file->getClientMimeType();
        }

        $response = $this->augmont->updateUserKyc($uniqueId, $data);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to update KYC'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC updated successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }
}
