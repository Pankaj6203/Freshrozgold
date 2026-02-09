<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\AugmontService;
use App\Models\PanVerification;
use App\Models\AadhaarVerification;
use App\Models\User;

class KycController extends Controller
{
    private $quickekycConfig;
    private $augmontService;

    public function __construct(AugmontService $augmontService)
    {
        $this->quickekycConfig = config('services.quickekyc');
        $this->augmontService = $augmontService;
    }
    /**
     * Verify PAN Card
     * No OTP required - Direct verification
     */
    public function verifyPan(Request $request)
    {
        try {
            // Validate input
            // Only PAN number is required
            $validator = Validator::make($request->all(), [
                'pan_number' => 'required|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input. Please check PAN number format (ABCDE1234F).',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $panNumber = strtoupper($request->pan_number);
            // Mobile is not required or searched for PAN verification
            $mobile = $request->input('mobile', null);

            // Check if PAN already verified
            $existingPan = PanVerification::where('pan_number', $panNumber)
                ->verified()
                ->first();

            if ($existingPan) {
                return response()->json([
                    'success' => true,
                    'message' => 'PAN already verified',
                    'data' => [
                        'pan_number' => $panNumber,
                        'name' => $existingPan->name,
                        'status' => $existingPan->status,
                        'verified_at' => $existingPan->verified_at,
                        'already_verified' => true
                    ],
                    'timestamp' => now()
                ], 200);
            }

            // Call PAN Verification API
            $apiResult = $this->callPanVerificationAPI($panNumber);

            if (!$apiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'PAN verification failed: ' . ($apiResult['error'] ?? 'Unknown error'),
                    'timestamp' => now()
                ], 400);
            }

            // Store PAN verification data
            $verificationId = 'PAN_' . time() . '_' . rand(1000, 9999);
            
            $insert = [
                'pan_number' => $panNumber,
                'name' => $apiResult['data']['name'] ?? null,
                'name_on_card' => $apiResult['data']['name_on_card'] ?? null,
                'father_name' => $apiResult['data']['father_name'] ?? null,
                'date_of_birth' => $apiResult['data']['date_of_birth'] ?? null,
                'status' => $apiResult['data']['status'] ?? 'valid',
                'category' => $apiResult['data']['category'] ?? null,
                'is_verified' => true,
                'verification_id' => $verificationId,
                'masked_image_url' => $apiResult['data']['masked_image'] ?? null,
                'api_response' => json_encode($apiResult['raw_response']),
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ];

            // include mobile only if available (do not insert null)
            if (!empty($mobile)) {
                $insert['mobile'] = $mobile;
            }

            PanVerification::create($insert);

            Log::info("PAN Verified Successfully - PAN: $panNumber, Mobile: $mobile");

            return response()->json([
                'success' => true,
                'message' => 'PAN verified successfully',
                'data' => [
                    'verification_id' => $verificationId,
                    'pan_number' => $panNumber,
                    'name' => $apiResult['data']['name'],
                    'name_on_card' => $apiResult['data']['name_on_card'] ?? null,
                    'status' => $apiResult['data']['status'],
                    'category' => $apiResult['data']['category'] ?? 'individual',
                    'verified_at' => now()
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("PAN Verification Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Generate Aadhaar OTP
     */
    public function aadhaarGenerateOtp(Request $request)
    {
        try {
            // Validate input
            // Only Aadhaar number is required for generating OTP
            $validator = Validator::make($request->all(), [
                'aadhaar_number' => 'required|digits:12'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input. Aadhaar must be 12 digits.',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $aadhaarNumber = $request->aadhaar_number;
            // Mobile is not required or searched for Aadhaar OTP generation
            $mobile = $request->input('mobile', null);

            // Call Aadhaar OTP Generation API (we pass only aadhaar_number to provider)
            $apiResult = $this->callAadhaarOtpAPI($aadhaarNumber);

            if (!$apiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate Aadhaar OTP: ' . ($apiResult['error'] ?? 'Unknown error'),
                    'timestamp' => now()
                ], 400);
            }

            // Store request for verification later (store mobile if we discovered it, otherwise null)
            $requestId = $apiResult['data']['request_id'];
            // Ensure request_id is string format for consistency
            $formattedRequestId = is_numeric($requestId) ? (string)$requestId : $requestId;

            $insert = [
                'aadhaar_number' => $aadhaarNumber,
                'request_id' => $formattedRequestId,
                'is_verified' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Only include mobile if we discovered it locally — do not store null mobile
            if (!empty($mobile)) {
                $insert['mobile'] = $mobile;
            }

            AadhaarVerification::create($insert);

            Log::info("Aadhaar OTP Generated - Aadhaar: $aadhaarNumber, RequestID: $formattedRequestId");

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to Aadhaar registered mobile number',
                'data' => [
                    'request_id' => $formattedRequestId,  // String format
                    'aadhaar_number' => 'XXXX XXXX ' . substr($aadhaarNumber, -4),
                    'message' => 'OTP sent to registered mobile number'
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Aadhaar OTP Generation Error: " . $e->getMessage());
            Log::error("Stack Trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Internal server error: ' . $e->getMessage() : 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Verify Aadhaar OTP and get details
     */
    public function aadhaarVerifyOtp(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'request_id' => 'required|string',
                'otp' => 'required|digits:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input. OTP must be 6 digits.',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $requestId = $request->request_id;
            $otp = $request->otp;
            
            Log::info("Aadhaar Verify OTP Request - RequestID: $requestId");

            // Find pending verification
            $pendingVerification = AadhaarVerification::where('request_id', $requestId)
                ->where('is_verified', false)
                ->orderBy('created_at', 'desc')
                ->first();
            
            Log::info("Pending Verification Found: " . ($pendingVerification ? 'Yes (ID: ' . $pendingVerification->id . ')' : 'No'));

            if (!$pendingVerification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired request ID',
                    'timestamp' => now()
                ], 400);
            }

            // Call Aadhaar Verification API
            $apiResult = $this->callAadhaarVerifyAPI($requestId, $otp);

            if (!$apiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar verification failed: ' . ($apiResult['error'] ?? 'Invalid OTP'),
                    'timestamp' => now()
                ], 400);
            }

            // Update verification record with Aadhaar details
            $verificationId = 'AADHAAR_' . time() . '_' . rand(1000, 9999);
            
            // Extract mobile from API response if available
            $mobileFromApi = $apiResult['data']['phone'] ?? null;
            
            AadhaarVerification::where('id', $pendingVerification->id)
                ->update([
                    'name' => $apiResult['data']['name'] ?? null,
                    'father_name' => $apiResult['data']['father_name'] ?? null,
                    'address' => $apiResult['data']['address'] ?? null,
                    'city' => $apiResult['data']['city'] ?? null,
                    'state' => $apiResult['data']['state'] ?? null,
                    'pincode' => $apiResult['data']['pincode'] ?? null,
                    'date_of_birth' => $apiResult['data']['date_of_birth'] ?? null,
                    // Store mobile number if available
                    'mobile' => $mobileFromApi,
                    'gender' => $apiResult['data']['gender'] ?? null,
                    'email' => $apiResult['data']['email'] ?? null,
                    'phone' => $mobileFromApi,
                    'masked_aadhaar_image' => $apiResult['data']['masked_image'] ?? null,
                    'profile_image' => $apiResult['data']['profile_image'] ?? null,
                    'is_verified' => true,
                    'verification_id' => $verificationId,
                    'api_response' => json_encode($apiResult['raw_response']),
                    'verified_at' => now(),
                    'updated_at' => now()
                ]);

            Log::info("Aadhaar Verified Successfully - RequestID: $requestId, VerificationID: $verificationId");

            return response()->json([
                'success' => true,
                'message' => 'Aadhaar verified successfully',
                'data' => [
                    'verification_id' => $verificationId,
                    'name' => $apiResult['data']['name'],
                    'father_name' => $apiResult['data']['father_name'] ?? null,
                    'date_of_birth' => $apiResult['data']['date_of_birth'] ?? null,
                    'gender' => $apiResult['data']['gender'] ?? null,
                    'mobile' => $mobileFromApi, // Return mobile in response
                    'address' => $apiResult['data']['address'] ?? null,
                    'city' => $apiResult['data']['city'] ?? null,
                    'state' => $apiResult['data']['state'] ?? null,
                    'pincode' => $apiResult['data']['pincode'] ?? null,
                    'masked_aadhaar_number' => 'XXXX XXXX ' . substr($pendingVerification->aadhaar_number, -4),
                    'masked_image' => $apiResult['data']['masked_image'] ?? null,
                    'profile_image' => $apiResult['data']['profile_image'] ?? null,
                    'verified_at' => now()
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Aadhaar Verification Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Internal server error: ' . $e->getMessage() : 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Submit KYC
     * Uses verified PAN and Aadhaar details to update user's KYC status locally and on Augmont
     */
    public function submitKyc(Request $request)
    {
        try {
            $user = $request->user();
            
            // If mobile is provided in request, use it (for unauthenticated flow or admin overrides)
            // Otherwise use authenticated user's mobile
            $mobile = $request->input('mobile', $user ? $user->mobile : null);
            
            if (!$mobile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mobile number is required',
                    'timestamp' => now()
                ], 400);
            }

            // 1. Get verified PAN details
            $panDetails = PanVerification::verified()
                ->where(function ($query) use ($mobile, $request) {
                    $query->where('mobile', $mobile);
                    if ($request->input('pan_number')) {
                        $query->orWhere('pan_number', $request->input('pan_number'));
                    }
                })
                ->orderBy('verified_at', 'desc')
                ->first();

            if (!$panDetails) {
                 return response()->json([
                    'success' => false,
                    'message' => 'PAN verification not found or pending. Please verify PAN first.',
                    'timestamp' => now()
                ], 400);
            }

            // 2. Get verified Aadhaar details
            $aadhaarDetails = AadhaarVerification::verified()
                ->where(function ($query) use ($mobile, $request) {
                    $query->where('mobile', $mobile);
                    if ($request->input('aadhaar_number')) {
                        $query->orWhere('aadhaar_number', $request->input('aadhaar_number'));
                    }
                })
                ->orderBy('verified_at', 'desc')
                ->first();

            if (!$aadhaarDetails) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar verification not found or pending. Please verify Aadhaar first.',
                    'timestamp' => now()
                ], 400);
            }

            // 3. Update User Table locally
            $updateData = [
                'pan' => $panDetails->pan_number,
                'aadhaar' => $aadhaarDetails->aadhaar_number,
                'kyc_status' => 'verified', // We consider local verification sufficient for "verified" status?
                // Or 'pending' until Augmont approves? Usually 'verified' if we trust QuickeKYC.
                // Let's set to 'verified' locally.
                'kyc_verified_at' => now()
            ];

            // If user doesn't exist, Create or Find?
            // User likely exists if they logged in.
            $userId = $user ? $user->id : null;
            if (!$userId) {
                // Try finding by mobile
                $existingUser = User::where('mobile', $mobile)->first();
                if ($existingUser) {
                    $userId = $existingUser->id;
                    $existingUser->update($updateData);
                }
                // If user doesn't exist at all, we can't submit KYC to Augmont without a User ID.
                else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found. Please register first.',
                        'timestamp' => now()
                    ], 404);
                }
            } else {
                 User::where('id', $userId)->update($updateData);
            }

            // Reload user to get unique_id for Augmont
            $userRecord = User::find($userId);
            $uniqueId = $userRecord->unique_id;

            if (!$uniqueId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'User unique ID missing for Augmont integration.',
                    'timestamp' => now()
                ], 400);
            }

            // 4. Send to Augmont (Update KYC)
            // Prepare payload for Augmont
            $augmontData = [
                'panNumber' => $panDetails->pan_number,
                'dateOfBirth' => $panDetails->date_of_birth ?? $aadhaarDetails->date_of_birth, // Prefer PAN DOB
                'nameAsPerPan' => $panDetails->name ?? $aadhaarDetails->name,
                // 'status' => 'pending' // Augmont usually sets status based on their checks
            ];

            // Handle PAN Attachment if provided in request
            if ($request->hasFile('pan_attachment')) {
                 $file = $request->file('pan_attachment');
                 $augmontData['panAttachment'] = base64_encode(file_get_contents($file->getRealPath()));
                 $augmontData['panAttachmentType'] = $file->getClientMimeType();
            }

            $augmontResponse = $this->augmontService->updateUserKyc($uniqueId, $augmontData);

            if (!$augmontResponse['success']) {
                // Log error but treat local verification as success? Or return partial success?
                Log::warning("Augmont KYC Update Failed for User $uniqueId: " . ($augmontResponse['message'] ?? 'Unknown Error'));
                
                return response()->json([
                    'success' => true, 
                    'message' => 'KYC submitted locally. Augmont sync pending.',
                    'warning' => 'Augmont Update Failed: ' . ($augmontResponse['message'] ?? 'Unknown'),
                    'timestamp' => now()
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC submitted successfully',
                'data' => [
                    'kyc_status' => 'verified',
                    'augmont_status' => $augmontResponse['data']['status'] ?? 'pending'
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Submit KYC Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get User KYC Status
     * 
     * Checks both PAN and Aadhaar verification tables
     */
    public function getKycStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mobile' => 'required|digits:10|regex:/^[6-9]\d{9}$/'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid mobile number',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $mobile = $request->mobile;

            // Check PAN Status
            $panVerified = PanVerification::where('mobile', $mobile)
                ->verified()
                ->exists();

            // Check Aadhaar Status
            $aadhaarVerified = AadhaarVerification::where('mobile', $mobile)
                ->verified()
                ->exists();

            $status = ($panVerified && $aadhaarVerified) ? 'verified' : (($panVerified || $aadhaarVerified) ? 'partial' : 'pending');

            return response()->json([
                'success' => true,
                'message' => 'KYC status retrieved successfully',
                'data' => [
                    'kyc_status' => $status,
                    'pan_verified' => $panVerified,
                    'aadhaar_verified' => $aadhaarVerified,
                    'timestamp' => now()
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("KYC Status Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Call PAN Verification API
     */
    private function callPanVerificationAPI($panNumber)
    {
        try {
            // Get API credentials from config
            $environment = $this->quickekycConfig['environment'];
            $apiKey = $environment === 'sandbox' 
                ? $this->quickekycConfig['sandbox_api_key'] 
                : $this->quickekycConfig['api_key'];
            $apiUrl = $environment === 'sandbox'
                ? $this->quickekycConfig['sandbox_url']
                : $this->quickekycConfig['api_url'];
            
            Log::info("PAN API Call - PAN: $panNumber, Environment: $environment");

            // Initialize cURL for QuickeKYC API
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl . '/api/v1/pan/pan',  // Correct QuickeKYC endpoint
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'key' => $apiKey,
                    'id_number' => $panNumber  // QuickeKYC uses 'id_number' parameter
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => ($this->quickekycConfig['environment'] ?? 'sandbox') === 'production',
                CURLOPT_SSL_VERIFYHOST => ($this->quickekycConfig['environment'] ?? 'sandbox') === 'production' ? 2 : 0
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \Exception('cURL Error: ' . $error);
            }
            
            curl_close($ch);

            Log::info("PAN API Response - HTTP: $httpCode");
            Log::info("PAN API Response Body: " . $response);

            $result = json_decode($response, true);

            // Handle QuickeKYC response format
            if ($httpCode === 200 && isset($result['status'])) {
                if ($result['status'] === 'success' && isset($result['data'])) {
                    $data = $result['data'];
                    
                    return [
                        'success' => true,
                        'data' => [
                            'name' => $data['full_name'] ?? $data['name'] ?? null,
                            'name_on_card' => $data['name_on_card'] ?? $data['full_name'] ?? null,
                            'father_name' => $data['fathers_name'] ?? $data['father_name'] ?? null,
                            'date_of_birth' => $data['dob'] ?? $data['date_of_birth'] ?? null,
                            'status' => 'valid',
                            'category' => $data['category'] ?? 'individual',
                            'masked_image' => $data['masked_pan_image'] ?? $data['masked_image'] ?? null
                        ],
                        'raw_response' => $result
                    ];
                } elseif ($result['status'] === 'error' || $result['status'] === 'failed') {
                    return [
                        'success' => false,
                        'error' => $result['message'] ?? 'PAN verification failed'
                    ];
                }
            }

            return [
                'success' => false,
                'error' => $result['message'] ?? 'Invalid API response'
            ];

        } catch (\Exception $e) {
            Log::error("PAN API Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Call Aadhaar OTP Generation API
     */
    private function callAadhaarOtpAPI($aadhaarNumber)
    {
        try {
            $environment = $this->quickekycConfig['environment'];
            $apiKey = $environment === 'sandbox' 
                ? $this->quickekycConfig['sandbox_api_key'] 
                : $this->quickekycConfig['api_key'];
            $apiUrl = $environment === 'sandbox'
                ? $this->quickekycConfig['sandbox_url']
                : $this->quickekycConfig['api_url'];
            
            Log::info("Aadhaar OTP API - Environment: $environment, URL: $apiUrl, API Key: " . substr($apiKey, 0, 8) . "...");
            
            // For testing without real API key - Mock response
            if (!$apiKey || $apiKey === 'your_kyc_api_key_here') {
                Log::info("Aadhaar OTP API - Using MOCK response for testing");
                
                sleep(1);
                
                return [
                    'success' => true,
                    'data' => [
                        'request_id' => 'MOCK_REQ_' . time() . '_' . rand(1000, 9999)
                    ],
                    'raw_response' => [
                        'status' => 'success',
                        'message' => 'OTP sent successfully (MOCK MODE - For testing only)',
                        'mock_mode' => true
                    ]
                ];
            }
            
            Log::info("Aadhaar OTP API Call - Aadhaar: ****" . substr($aadhaarNumber, -4));

            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl . '/api/v1/aadhaar-v2/generate-otp',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'key' => $apiKey,
                    'id_number' => $aadhaarNumber
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => ($this->quickekycConfig['environment'] ?? 'sandbox') === 'production',
                CURLOPT_SSL_VERIFYHOST => ($this->quickekycConfig['environment'] ?? 'sandbox') === 'production' ? 2 : 0
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new \Exception('cURL Error: ' . curl_error($ch));
            }
            
            curl_close($ch);

            Log::info("Aadhaar OTP API Response - HTTP: $httpCode");
            Log::info("Aadhaar OTP API Raw Response: " . substr($response, 0, 500));

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success') {
                // Get request_id from API - it returns as integer or string
                $apiRequestId = $result['data']['request_id'] ?? $result['request_id'] ?? null;
                
                // Convert to string if it's numeric to ensure consistent format
                $requestId = $apiRequestId !== null ? (string)$apiRequestId : 'REQ_' . time() . '_' . rand(1000, 9999);
                
                Log::info("Aadhaar OTP Generated Successfully - RequestID: " . $requestId);
                return [
                    'success' => true,
                    'data' => [
                        'request_id' => $requestId
                    ],
                    'raw_response' => $result
                ];
            }

            Log::warning("Aadhaar OTP Generation Failed - HTTP: $httpCode, Message: " . ($result['message'] ?? 'no message'));
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to generate OTP'
            ];

        } catch (\Exception $e) {
            Log::error("Aadhaar OTP API Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Call Aadhaar Verification API
     */
    private function callAadhaarVerifyAPI($requestId, $otp)
    {
        try {
            $apiKey = $this->quickekycConfig['environment'] === 'sandbox' 
                ? $this->quickekycConfig['sandbox_api_key'] 
                : $this->quickekycConfig['api_key'];
            $apiUrl = $this->quickekycConfig['environment'] === 'sandbox'
                ? $this->quickekycConfig['sandbox_url']
                : $this->quickekycConfig['api_url'];
            
            Log::info("Aadhaar Verify API Call - RequestID: $requestId");

            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl . '/api/v1/aadhaar-v2/submit-otp',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'key' => $apiKey,
                    'request_id' => $requestId,
                    'otp' => $otp
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => ($this->quickekycConfig['environment'] ?? 'sandbox') === 'production',
                CURLOPT_SSL_VERIFYHOST => ($this->quickekycConfig['environment'] ?? 'sandbox') === 'production' ? 2 : 0
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new \Exception('cURL Error: ' . curl_error($ch));
            }
            
            curl_close($ch);

            Log::info("Aadhaar Verify API Response - HTTP: $httpCode");
            Log::info("Aadhaar Verify API Raw Response: " . $response);

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success') {
                $data = $result['data'] ?? [];
                
                // Map gender from API format to database enum format
                $genderMap = [
                    'M' => 'male',
                    'F' => 'female',
                    'MALE' => 'male',
                    'FEMALE' => 'female',
                    'male' => 'male',
                    'female' => 'female',
                    'other' => 'other'
                ];
                $apiGender = strtoupper($data['gender'] ?? 'OTHER');
                $gender = $genderMap[$apiGender] ?? 'other';
                
                // Handle address - could be string or array
                $address = $data['address'] ?? $data['full_address'] ?? null;
                if (is_array($address)) {
                    // Convert address array to readable string
                    $addressParts = [];
                    if (!empty($address['house'])) $addressParts[] = $address['house'];
                    if (!empty($address['street'])) $addressParts[] = $address['street'];
                    if (!empty($address['landmark'])) $addressParts[] = $address['landmark'];
                    if (!empty($address['loc'])) $addressParts[] = $address['loc'];
                    if (!empty($address['vtc'])) $addressParts[] = $address['vtc'];
                    if (!empty($address['po'])) $addressParts[] = $address['po'];
                    if (!empty($address['dist'])) $addressParts[] = $address['dist'];
                    if (!empty($address['subdist'])) $addressParts[] = $address['subdist'];
                    if (!empty($address['state'])) $addressParts[] = $address['state'];
                    if (!empty($address['country'])) $addressParts[] = $address['country'];
                    $address = implode(', ', $addressParts);
                }
                
                return [
                    'success' => true,
                    'data' => [
                        'name' => $data['name'] ?? $data['full_name'] ?? null,
                        'father_name' => $data['father_name'] ?? $data['care_of'] ?? null,
                        'date_of_birth' => $data['dob'] ?? $data['date_of_birth'] ?? null,
                        'gender' => $gender,
                        'address' => $data['address'] ?? $data['full_address'] ?? null,
                        'city' => $data['city'] ?? $data['district'] ?? null,
                        'state' => $data['state'] ?? null,
                        'pincode' => $data['pincode'] ?? $data['zip'] ?? null,
                        'email' => $data['email'] ?? null,
                        'phone' => $data['phone'] ?? $data['mobile'] ?? null,
                        'masked_image' => $data['masked_aadhaar'] ?? $data['aadhaar_pdf'] ?? null,
                        'profile_image' => $data['photo'] ?? $data['profile_image'] ?? null
                    ],
                    'raw_response' => $result
                ];
            }

            Log::warning("Aadhaar Verify API Failed - Status: " . ($result['status'] ?? 'unknown') . ", Message: " . ($result['message'] ?? 'no message'));
            
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Invalid OTP or verification failed'
            ];

        } catch (\Exception $e) {
            Log::error("Aadhaar Verify API Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
