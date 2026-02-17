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
                    'success' => false,
                    'message' => 'PAN number already verified. Cannot verify the same PAN again.',
                    'data' => [
                        'pan_number' => $panNumber,
                        'already_verified' => true,
                        'verified_at' => $existingPan->verified_at
                    ],
                    'timestamp' => now()
                ], 400);
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
            
            // Map API category 'person' to db enum 'individual'
            $apiCategory = strtolower($apiResult['data']['category'] ?? '');
            $dbCategory = 'other'; // default
            if ($apiCategory === 'person' || $apiCategory === 'individual') {
                $dbCategory = 'individual';
            } elseif (in_array($apiCategory, ['company', 'firm', 'trust'])) {
                $dbCategory = $apiCategory;
            } elseif (empty($apiCategory)) {
                $dbCategory = null; 
            }

            $insert = [
                'pan_number' => $panNumber,
                'name' => $apiResult['data']['name'] ?? null,
                'name_on_card' => $apiResult['data']['name_on_card'] ?? null,
                'father_name' => $apiResult['data']['father_name'] ?? null,
                'date_of_birth' => $apiResult['data']['date_of_birth'] ?? null,
                'status' => $apiResult['data']['status'] ?? 'valid',
                'category' => $dbCategory,
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

            // Update User KYC Status
            $this->_updateUserKycStatus($mobile);

            return response()->json([
                'success' => true,
                'message' => 'PAN verified successfully',
                'data' => [
                    'verification_id' => $verificationId,
                    'pan_number' => $panNumber,
                    'name' => $apiResult['data']['name'],
                    'name_on_card' => $apiResult['data']['name_on_card'] ?? null,
                    'date_of_birth' => $apiResult['data']['date_of_birth'] ?? null,
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

            // Check if Aadhaar already verified
            $existingAadhaar = AadhaarVerification::where('aadhaar_number', $aadhaarNumber)
                ->verified()
                ->first();

            if ($existingAadhaar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar number already verified. Cannot generate OTP for already verified Aadhaar.',
                    'data' => [
                        'aadhaar_number' => 'XXXX XXXX ' . substr($aadhaarNumber, -4),
                        'already_verified' => true,
                        'verified_at' => $existingAadhaar->verified_at
                    ],
                    'timestamp' => now()
                ], 400);
            }

            // Call Aadhaar OTP Generation API (we pass only aadhaar_number to provider)
            $apiResult = $this->callAadhaarOtpAPI($aadhaarNumber);

            if (!$apiResult['success']) {
                // Log full details for server-side debugging
                Log::error("Aadhaar OTP API failed", [
                    'aadhaar_last4' => substr($aadhaarNumber, -4),
                    'error' => $apiResult['error'] ?? 'Unknown',
                    'http_code' => $apiResult['http_code'] ?? null,
                    'raw_response' => $apiResult['raw_response'] ?? null,
                    'environment' => $this->quickekycConfig['environment'] ?? 'unknown',
                    'api_url' => ($this->quickekycConfig['environment'] === 'sandbox')
                        ? $this->quickekycConfig['sandbox_url'] 
                        : $this->quickekycConfig['api_url'],
                ]);

                // Robust provider status extraction: prefer explicit http_code, fallback to provider's raw_response.status_code
                $providerCode = null;
                if (isset($apiResult['http_code'])) {
                    $providerCode = intval($apiResult['http_code']);
                } elseif (!empty($apiResult['raw_response']['status_code'])) {
                    $providerCode = intval($apiResult['raw_response']['status_code']);
                } elseif (!empty($apiResult['raw_response']['http_code'])) {
                    $providerCode = intval($apiResult['raw_response']['http_code']);
                }

                // Provider rejected credentials → surface as upstream problem (Bad Gateway)
                if ($providerCode === 401) {
                    Log::warning("Aadhaar OTP generation unauthorized (QuickeKYC) for Aadhaar: ****" . substr($aadhaarNumber, -4));
                    return response()->json([
                        'success' => false,
                        'message' => 'Aadhaar service unauthorized. Check KYC provider API key/configuration.',
                        'errors'  => null
                    ], 502);
                }

                // Provider service error → Service Unavailable
                if ($providerCode !== null && $providerCode >= 500) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aadhaar service temporarily unavailable. Please try again later.',
                        'errors'  => null
                    ], 503);
                }

                // Fallback: keep as bad request when provider returned a client-side error we can surface
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate Aadhaar OTP: ' . ($apiResult['error'] ?? 'Unknown error'),
                    'errors'  => null
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

            // Check if a pending verification request already exists for this Aadhaar number
            // If so, update it instead of creating a new row, to avoid unique constraint violation
            $pendingRecord = AadhaarVerification::where('aadhaar_number', $aadhaarNumber)
                ->where('is_verified', false)

                ->first();

            if ($pendingRecord) {
                $pendingRecord->update(array_merge($insert, ['updated_at' => now()]));
                Log::info("Existing pending Aadhaar record updated - Aadhaar: $aadhaarNumber, RequestID: $formattedRequestId");
            } else {
                AadhaarVerification::create($insert);
                Log::info("New Aadhaar record created - Aadhaar: $aadhaarNumber, RequestID: $formattedRequestId");
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to Aadhaar registered mobile number',
                'data' => [
                    'request_id' => $formattedRequestId,
                    'aadhaar_number' => 'XXXX XXXX ' . substr($aadhaarNumber, -4)
                ]
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
                // IDEMPOTENCY CHECK:
                // Check if this request_id was ALREADY verified (in case frontend is retrying after a timeout/error)
                $alreadyVerified = AadhaarVerification::where('request_id', $requestId)
                    ->where('is_verified', true)
                    ->first();

                if ($alreadyVerified) {
                    Log::info("Duplicate Aadhaar OTP verification request for Verified ID: {$alreadyVerified->id}. Returning previous success.");
                    
                    // Trigger status update just in case it failed previously
                    $this->_updateUserKycStatus($alreadyVerified->mobile);

                    return response()->json([
                        'success' => true,
                        'message' => 'Aadhaar already verified',
                        'data' => [
                            'verification_id' => $alreadyVerified->verification_id,
                            'name' => $alreadyVerified->name,
                            'father_name' => $alreadyVerified->father_name,
                            'date_of_birth' => $alreadyVerified->date_of_birth,
                            'gender' => $alreadyVerified->gender,
                            'mobile' => $alreadyVerified->mobile,
                            'address' => $alreadyVerified->address,
                            'city' => $alreadyVerified->city,
                            'state' => $alreadyVerified->state,
                            'pincode' => $alreadyVerified->pincode,
                            'masked_aadhaar_number' => 'XXXX XXXX ' . substr($alreadyVerified->aadhaar_number, -4),
                            'masked_image' => $alreadyVerified->masked_aadhaar_image,
                            'profile_image' => $alreadyVerified->profile_image,
                            'verified_at' => $alreadyVerified->verified_at
                        ],
                        'timestamp' => now()
                    ], 200);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired request ID',
                    'timestamp' => now()
                ], 400);
            }
            
            // Ensure mobile number is not null in the pending record before update
            // If it's null, try to fetch it from the authenticated user or request
            if (empty($pendingVerification->mobile)) {
                $user = $request->user();
                $mobile = $request->input('mobile', $user ? $user->mobile : null);
                
                if ($mobile) {
                    $pendingVerification->mobile = $mobile;
                    $pendingVerification->save(); // Save immediately so UPDATE query below doesn't fail
                    Log::info("Fixed missing mobile for pending Aadhaar verification ID: {$pendingVerification->id}");
                } else {
                    // Fallback to a dummy mobile if absolutely necessary to prevent crash, 
                    // though this should be caught by validation earlier.
                    // But for strict SQL mode, we need a value.
                    // Let's use the phone from API response if available later, 
                    // but for THIS specific error, we need to ensure the record has a mobile before update?
                    // actually the error is on UPDATE `aadhaar_verifications` ... `mobile` = ? 
                    // where `mobile` is coming from $mobileFromApi which might be null.
                }
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
            
            // If phone is missing from API, retain the existing mobile from the DB record
            // THIS FIXES THE "Column 'mobile' cannot be null" ERROR
            if (empty($mobileFromApi)) {
                $mobileFromApi = $pendingVerification->mobile;
            }

            // If still null (rare edge case), fallback to Auth user mobile
            if (empty($mobileFromApi)) {
                $user = $request->user();
                $mobileFromApi = $user ? $user->mobile : null; 
            }
            
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

            // Update User KYC Status
            $this->_updateUserKycStatus($mobileFromApi);

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
                'kyc_status' => 'verified',
                'kyc_verified_at' => now(),
            ];

            // If name is missing, use name from documents
            if (empty($user->name) || str_starts_with($user->name, 'User_')) {
               $updateData['name'] = $panDetails->name ?? $aadhaarDetails->name;
            }

            // If user doesn't exist, Create or Find?
            $userId = $user ? $user->id : null;
            if (!$userId) {
                $existingUser = User::where('mobile', $mobile)->first();
                if ($existingUser) {
                     $existingUser->update($updateData);
                }
            } else {
                 User::where('id', $userId)->update($updateData);
            }

            // 4. NOTE: Augmont Account is created at Profile Setup step.
            // We just update the local KYC status here. 
            // If you need to sync KYC changes to Augmont later (Update Augmont User), 
            // you can call $this->augmontService->updateUser(...) here.
            
            return response()->json([
                'success' => true,
                'message' => 'KYC submitted successfully',
                'data' => [
                    'kyc_status' => 'verified',
                    'pan' => $panDetails->pan_number,
                    'aadhaar' => $aadhaarDetails->aadhaar_number
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {

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
            $panRecord = PanVerification::where('mobile', $mobile)
                ->verified()
                ->latest()
                ->first();
            
            $panVerified = $panRecord ? true : false;

            // Check Aadhaar Status
            $aadhaarRecord = AadhaarVerification::where('mobile', $mobile)
                ->verified()
                ->latest()
                ->first();

            $aadhaarVerified = $aadhaarRecord ? true : false;

            $status = ($panVerified && $aadhaarVerified) ? 'verified' : 'pending';

            // Sync with User table if exists
            // This ensures older records get updated when status is checked
            $user = User::where('mobile', $mobile)->first();
            if ($user) {
                if ($user->kyc_status !== $status) {
                    $user->kyc_status = $status;
                    if ($status === 'verified' && !$user->kyc_verified_at) {
                        $user->kyc_verified_at = now();
                    }
                    $user->save();
                    Log::info("Synced user KYC status for mobile {$mobile} to: {$status} in getKycStatus");
                }
            }
            
            // Mask Aadhaar Number (Show last 4 digits)
            $maskedAadhaar = null;
            if ($aadhaarRecord && !empty($aadhaarRecord->aadhaar_number)) {
                $rawAadhaar = preg_replace('/[^0-9]/', '', $aadhaarRecord->aadhaar_number);
                if (strlen($rawAadhaar) >= 4) {
                    $maskedAadhaar = 'XXXXXXXX' . substr($rawAadhaar, -4);
                } else {
                    $maskedAadhaar = 'XXXXXXXX' . $rawAadhaar;
                }
            }

            // Split name helper
            $firstName = '';
            $lastName = '';
            
            $fullName = null;
            if ($panRecord && $panRecord->name) {
                $fullName = $panRecord->name;
            } elseif ($aadhaarRecord && $aadhaarRecord->name) {
                $fullName = $aadhaarRecord->name;
            } elseif ($user && $user->name) {
                $fullName = $user->name;
            }

            if ($fullName) {
                $parts = explode(' ', trim($fullName));
                if (count($parts) > 0) {
                    $firstName = $parts[0];
                    $lastName = (count($parts) > 1) ? end($parts) : '';
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC status retrieved successfully',
                'data' => [
                    'kyc_status' => $status,
                    'pan_verified' => $panVerified,
                    'aadhaar_verified' => $aadhaarVerified,
                    
                    // Detailed fields for form pre-fill
                    'pan_number' => $panRecord ? $panRecord->pan_number : null,
                    'name_as_per_pan' => $panRecord ? $panRecord->name : null,
                    'date_of_birth' => $panRecord && $panRecord->date_of_birth
                        ? ($panRecord->date_of_birth instanceof \DateTimeInterface ? $panRecord->date_of_birth->format('Y-m-d') : $panRecord->date_of_birth)
                        : ($aadhaarRecord && $aadhaarRecord->date_of_birth
                            ? ($aadhaarRecord->date_of_birth instanceof \DateTimeInterface ? $aadhaarRecord->date_of_birth->format('Y-m-d') : $aadhaarRecord->date_of_birth)
                            : null),
                    
                    'aadhaar_number' => $maskedAadhaar,
                    'aadhaar_data' => $aadhaarRecord ? [
                         'name' => $aadhaarRecord->name,
                         'dob' => $aadhaarRecord->date_of_birth instanceof \DateTimeInterface ? $aadhaarRecord->date_of_birth->format('Y-m-d') : $aadhaarRecord->date_of_birth,
                         'address' => $aadhaarRecord->address,
                         'state' => $aadhaarRecord->state,
                         'city' => $aadhaarRecord->city,
                         'pincode' => $aadhaarRecord->pincode,
                     ] : null,
                     
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $user ? $user->email : ($aadhaarRecord ? $aadhaarRecord->email : null),

                    'timestamp' => now()
                ],
                // Backward compatibility alias
                'kyc_details' => [
                     'status' => $status,
                     'pan_number' => $panRecord ? $panRecord->pan_number : null,
                     'name_as_per_pan' => $panRecord ? $panRecord->name : null,
                     'date_of_birth' => $panRecord && $panRecord->date_of_birth
                         ? ($panRecord->date_of_birth instanceof \DateTimeInterface ? $panRecord->date_of_birth->format('Y-m-d') : $panRecord->date_of_birth)
                         : ($aadhaarRecord && $aadhaarRecord->date_of_birth
                             ? ($aadhaarRecord->date_of_birth instanceof \DateTimeInterface ? $aadhaarRecord->date_of_birth->format('Y-m-d') : $aadhaarRecord->date_of_birth)
                             : null),
                     'aadhaar_number' => $maskedAadhaar,
                     'first_name' => $firstName,
                     'last_name' => $lastName,
                     'email' => $user ? $user->email : ($aadhaarRecord ? $aadhaarRecord->email : null),
                     'aadhaar_verified' => $aadhaarVerified,
                     'pan_verified' => $panVerified,
                     'submitted_at' => $panRecord ? $panRecord->verified_at : ($aadhaarRecord ? $aadhaarRecord->verified_at : null),
                     'verified_at' => ($panVerified && $aadhaarVerified) ? ($user ? $user->kyc_verified_at : now()) : null,
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
        $environment = $this->quickekycConfig['environment'];
        $apiKey = $environment === 'sandbox' 
            ? $this->quickekycConfig['sandbox_api_key'] 
            : $this->quickekycConfig['api_key'];
        $apiUrl = $environment === 'sandbox'
            ? $this->quickekycConfig['sandbox_url']
            : $this->quickekycConfig['api_url'];
        
        Log::info("Aadhaar OTP API - Environment: $environment, URL: $apiUrl, API Key: " . substr($apiKey, 0, 8) . "...");
        Log::info("Aadhaar OTP API Call - Aadhaar: ****" . substr($aadhaarNumber, -4));

        $maxAttempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $ch = curl_init();
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl . '/api/v1/aadhaar-v2/generate-otp',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode([
                        'key' => $apiKey,
                        'id_number' => $aadhaarNumber
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json'
                    ],
                    CURLOPT_SSL_VERIFYPEER => $environment === 'production',
                    CURLOPT_SSL_VERIFYHOST => $environment === 'production' ? 2 : 0
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if (curl_errno($ch)) {
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    throw new \Exception('cURL Error: ' . $curlError);
                }
                
                curl_close($ch);

                Log::info("Aadhaar OTP API Response (attempt {$attempt}) - HTTP: $httpCode");
                Log::info("Aadhaar OTP API Raw Response: " . substr($response, 0, 500));

                $result = json_decode($response, true);

                // ── Success ──
                if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success') {
                    $apiRequestId = $result['data']['request_id'] ?? $result['request_id'] ?? null;
                    $requestId = $apiRequestId !== null ? (string)$apiRequestId : 'REQ_' . time() . '_' . rand(1000, 9999);
                    
                    Log::info("Aadhaar OTP Generated Successfully - RequestID: " . $requestId);
                    return [
                        'success' => true,
                        'data' => ['request_id' => $requestId],
                        'raw_response' => $result
                    ];
                }

                // ── UIDAI backend error (HTTP 200 but status_code 500) — retry ──
                $internalStatusCode = $result['status_code'] ?? null;
                $isUidaiBackendError = $httpCode === 200 
                    && ($internalStatusCode == 500 || ($result['status'] ?? '') === 'error')
                    && str_contains(strtolower($result['message'] ?? ''), 'error from backend');

                if ($isUidaiBackendError && $attempt < $maxAttempts) {
                    Log::warning("UIDAI backend error (attempt {$attempt}/{$maxAttempts}), retrying in 2s...", [
                        'status_code' => $internalStatusCode,
                        'message' => $result['message'] ?? '',
                        'request_id' => $result['request_id'] ?? null,
                    ]);
                    sleep(2);
                    continue;
                }

                // ── Final failure — return appropriate message ──
                $errorMessage = $result['message'] ?? 'Failed to generate OTP';
                if ($isUidaiBackendError) {
                    $errorMessage = 'UIDAI service is temporarily unavailable. Please try again after a few minutes.';
                }

                Log::warning("Aadhaar OTP Generation Failed - HTTP: $httpCode, Message: " . ($result['message'] ?? 'no message'));
                return [
                    'success'      => false,
                    'http_code'    => $httpCode,
                    'error'        => $errorMessage,
                    'raw_response' => $result,
                ];

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $isDnsOrTimeout = str_contains($lastError, 'Could not resolve')
                    || str_contains($lastError, 'Resolving timed out')
                    || str_contains($lastError, 'timed out');

                Log::error("Aadhaar OTP API Error (attempt {$attempt}/{$maxAttempts}): " . $lastError);

                if ($isDnsOrTimeout && $attempt < $maxAttempts) {
                    sleep(1);
                    continue;
                }
            }
        }

        return [
            'success'   => false,
            'http_code' => null,
            'error'     => $lastError ?? 'Aadhaar OTP API failed after retries',
        ];
    }

    /**
     * Call Aadhaar Verification API
     */
    private function callAadhaarVerifyAPI($requestId, $otp)
    {
        $apiKey = $this->quickekycConfig['environment'] === 'sandbox' 
            ? $this->quickekycConfig['sandbox_api_key'] 
            : $this->quickekycConfig['api_key'];
        $apiUrl = $this->quickekycConfig['environment'] === 'sandbox'
            ? $this->quickekycConfig['sandbox_url']
            : $this->quickekycConfig['api_url'];
        
        Log::info("Aadhaar Verify API Call - RequestID: $requestId");

        $maxAttempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $ch = curl_init();
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl . '/api/v1/aadhaar-v2/submit-otp',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
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
                    CURLOPT_SSL_VERIFYPEER => $this->quickekycConfig['environment'] === 'production',
                    CURLOPT_SSL_VERIFYHOST => $this->quickekycConfig['environment'] === 'production' ? 2 : 0
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if (curl_errno($ch)) {
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    throw new \Exception('cURL Error: ' . $curlError);
                }
                
                curl_close($ch);

                Log::info("Aadhaar Verify API Response (attempt {$attempt}) - HTTP: $httpCode");
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
            
                // Check for UIDAI backend error — retry
                $internalStatusCode = $result['status_code'] ?? null;
                $isUidaiBackendError = $httpCode === 200 
                    && ($internalStatusCode == 500 || ($result['status'] ?? '') === 'error')
                    && str_contains(strtolower($result['message'] ?? ''), 'error from backend');

                if ($isUidaiBackendError && $attempt < $maxAttempts) {
                    Log::warning("UIDAI backend error on verify (attempt {$attempt}/{$maxAttempts}), retrying in 2s...");
                    sleep(2);
                    continue;
                }

                $errorMessage = $result['message'] ?? 'Invalid OTP or verification failed';
                if ($isUidaiBackendError) {
                    $errorMessage = 'UIDAI service is temporarily unavailable. Please try again after a few minutes.';
                }

                return [
                    'success' => false,
                    'error' => $errorMessage,
                ];

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Aadhaar Verify API Error (attempt {$attempt}/{$maxAttempts}): " . $lastError);

                $isDnsOrTimeout = str_contains($lastError, 'Could not resolve')
                    || str_contains($lastError, 'timed out');

                if ($isDnsOrTimeout && $attempt < $maxAttempts) {
                    sleep(1);
                    continue;
                }
            }
        }

        return [
            'success' => false,
            'error' => $lastError ?? 'Aadhaar verification failed after retries',
        ];
    }

    /**
     * Update User KYC Status based on PAN and Aadhaar
     */
    private function _updateUserKycStatus($mobile)
    {
        if (empty($mobile)) {
            return;
        }

        $user = User::where('mobile', $mobile)->first();
        if (!$user) {
            return;
        }

        $panVerified = PanVerification::where('mobile', $mobile)->where('is_verified', true)->exists();
        $aadhaarVerified = AadhaarVerification::where('mobile', $mobile)->where('is_verified', true)->exists();

        $status = 'pending';
        // Only set verified if BOTH are verified
        if ($panVerified && $aadhaarVerified) {
            $status = 'verified';
            $user->kyc_verified_at = now();
        } elseif ($panVerified || $aadhaarVerified) {
            $status = 'pending';
        }

        $user->kyc_status = $status;
        $user->save();
        
        Log::info("Updated user KYC status for mobile {$mobile} to: {$status}");
    }
}
