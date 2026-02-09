<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Jobs\SendOtpSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OtpController extends Controller
{
    private $smsmenowConfig;

    public function __construct()
    {
        $this->smsmenowConfig = config('services.smsmenow');
    }
    /**
     * Send OTP to mobiles number
     */
    public function sendOtp(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'mobiles' => 'required|digits:10|regex:/^[6-9]\d{9}$/'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid mobiles number format. Please enter a 10-digit mobiles number.',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $mobiles = $request->mobiles;

            // Test mode: bypass SMS for configured numbers (non-production only)
            $testNumbers = array_filter(explode(',', config('otp.test_numbers', '')));
            if (config('app.env') !== 'production' && in_array($mobiles, $testNumbers)) {
                 $otp = config('otp.test_otp', '123456');
                 
                 // Expire previous valid OTPs for this mobile
                 Otp::where('mobiles', $mobiles)
                     ->where('is_verified', false)
                     ->update(['is_verified' => true]);

                 // Store OTP in database
                 Otp::create([
                    'mobiles' => $mobiles,
                    'otp' => $otp,
                    'expires_at' => Carbon::now()->addMinutes(config('otp.validity_minutes')),
                    'is_verified' => false
                 ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully (Test Mode)',
                    'data' => [
                        'mobile' => $mobiles,
                        'expires_in_minutes' => config('otp.validity_minutes')
                    ],
                    'timestamp' => now()
                ], 200);
            }

            // Generate OTP (configurable length)
            $otpLength = config('otp.length');
            $maxOtpValue = (int) str_repeat('9', $otpLength);
            $otp = str_pad(random_int(0, $maxOtpValue), $otpLength, '0', STR_PAD_LEFT);

            // Calculate expiry time
            $otpValidityMinutes = config('otp.validity_minutes');
            $expiresAt = Carbon::now()->addMinutes($otpValidityMinutes);

            // Expire any previous unverified OTPs for this mobiles
            Otp::forMobile($mobiles)
                ->where('is_verified', false)
                ->update(['is_verified' => true]);

            // Store OTP in database
            Otp::create([
                'mobiles' => $mobiles,
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'is_verified' => false,
            ]);

            // Send OTP via SMS only if SMSMeNow is enabled in .env
            if (config('services.smsmenow.enabled')) {
                SendOtpSms::dispatch($mobiles, $otp);
                Log::info("SMS sent to {$mobiles} via SMSMeNow");
            } else {
                Log::info("SMS disabled (SMSMENOW_ENABLED=false) - OTP for {$mobiles}: {$otp}");
            }

            // Prepare response - include OTP when SMS is disabled
            $responseData = [
                'mobiles' => $mobiles,
                'expires_in' => config('otp.validity_minutes') * 60
            ];

            if (!config('services.smsmenow.enabled')) {
                $responseData['otp'] = $otp;
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'data' => $responseData,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Send OTP Error: " . $e->getMessage());
            Log::error("Stack Trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        try {
            // Validate input
            $otpLength = config('otp.length');
            $validator = Validator::make($request->all(), [
                'mobiles' => 'required|digits:10|regex:/^[6-9]\d{9}$/',
                'otp' => "required|digits:{$otpLength}"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing or invalid required fields',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $mobiles = $request->mobiles;
            $otp = $request->otp;

            // Verify OTP from database (hash-based comparison)
            $otpRecord = Otp::forMobile($mobiles)
                ->valid()
                ->latest()
                ->first();

            if (!$otpRecord || $otpRecord->otp !== $otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP',
                    'timestamp' => now()
                ], 400);
            }

            // Mark OTP as verified
            $otpRecord->markVerified();

            // Find or create the user (mobile-login flow) and issue Sanctum token
            $user = User::firstOrCreate(
                ['mobile' => $mobiles],
                [
                    'name'            => 'User ' . substr($mobiles, -4),
                    'email'           => $mobiles . '@noemail.local',
                    'password'        => Hash::make(Str::random(24)),
                    'unique_id'       => 'USR' . strtoupper(Str::random(10)),
                    'kyc_status'      => 'pending',
                ]
            );

            $token = $user->createToken('auth-token')->plainTextToken;

            // Log successful verification + login
            Log::info("OTP verified and token issued for mobile: {$mobiles}, user_id: {$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'OTP verified and login successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => $user->only(['id','name','email','mobile','unique_id','kyc_status'])
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Verify OTP Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Send OTP via SMS (SMSMenow Integration)
     */
    private function sendOtpSms($mobiles, $otp)
    {
        try {
            // SMS configuration from services config
            $smsmenowUser = $this->smsmenowConfig['user'];
            $smsmenowPassword = $this->smsmenowConfig['password'];
            $smsmenowSenderId = $this->smsmenowConfig['senderid'];
            $smsmenowUrl = $this->smsmenowConfig['url'];
            $jsessionId = config('services.smsmenow.jsessionid', '039173125348E90D79475DE345B52C29');
            
            // Log SMS attempt (without sensitive OTP)
            Log::info("=== SMS SEND START ===");
            Log::info("SMS Send Attempt - mobile: $mobiles, User: $smsmenowUser");
            Log::info("SMS URL: $smsmenowUrl");

            // Check if cURL is available
            if (!function_exists('curl_init')) {
                Log::error("cURL is not installed or enabled on this server!");
                throw new \Exception('cURL extension is not available');
            }

            // Format mobiles number
            $formattedMobiles = $mobiles;
            if (!str_starts_with($mobiles, '+')) {
                if (strlen($mobiles) === 10 && preg_match('/^[6-9]\d{9}$/', $mobiles)) {
                    $formattedMobiles = '+91' . $mobiles;
                }
            }
            Log::info("Formatted mobiles: $formattedMobiles");

            // Create SMS message
            $otpValidityMinutes = config('otp.validity_minutes');
            $smsTemplate = config('otp.sms_template');
            $message = str_replace(['{otp}', '{validity}'], [$otp, $otpValidityMinutes], $smsTemplate);
            
            Log::info("SMS message prepared for mobile: $formattedMobiles");

            // Initialize cURL
            $ch = curl_init();
            
            if ($ch === false) {
                Log::error("Failed to initialize cURL!");
                throw new \Exception('Failed to initialize cURL');
            }

            // Build the URL with parameters - exactly as per working curl command
            $url = $smsmenowUrl . '?' . http_build_query([
                'user'      => $smsmenowUser,
                'password'  => $smsmenowPassword,
                'senderid'  => $smsmenowSenderId,
                'mobiles'   => $formattedMobiles,
                'sms'       => $message,
            ]);
                   
            Log::info("Full SMS URL (masked password): " . preg_replace('/password=[^&]*/', 'password=****', $url));

            // Set cURL options - match working curl command (POST with empty body, no Cookie)
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '', // Empty POST body as per working curl
                CURLOPT_SSL_VERIFYPEER => config('app.env') === 'production',
                CURLOPT_SSL_VERIFYHOST => config('app.env') === 'production' ? 2 : false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            Log::info("cURL initialized, executing request...");

            // Execute the request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            Log::info("cURL executed - HTTP Code: $httpCode, Error No: $curlErrno");

            if ($curlErrno !== 0) {
                Log::error("cURL Error #$curlErrno: $curlError");
                curl_close($ch);
                throw new \Exception("cURL Error #$curlErrno: $curlError");
            }

            curl_close($ch);

            // Log the response
            Log::info("SMSMenow API Raw Response: " . ($response ?: 'EMPTY RESPONSE'));
            Log::info("SMSMenow HTTP Code: " . $httpCode);

            // Also log locally for development
            $this->logOtpLocally($mobiles, $otp);

            // Check for success response
            if ($httpCode == 200 && $response && strpos($response, '<status>success</status>') !== false) {
                Log::info("✓ SMS Sent Successfully - mobiles: $mobiles");
                Log::info("=== SMS SEND END (SUCCESS) ===");
                return [
                    'success' => true,
                    'message_id' => time(),
                    'status' => 'sent',
                    'message' => 'OTP sent successfully via SMS'
                ];
            } else {
                Log::error("✗ SMSMenow SMS Failed - HTTP: $httpCode");
                Log::error("Response: " . ($response ?: 'NO RESPONSE'));
                Log::error("=== SMS SEND END (FAILED) ===");
                
                return [
                    'success' => false,
                    'error' => 'SMS service failed: ' . substr($response ?: 'No response from SMS gateway', 0, 100),
                    'http_code' => $httpCode,
                    'fallback' => 'OTP logged locally due to SMS failure'
                ];
            }

        } catch (\Exception $e) {
            Log::error("✗ SMSMenow SMS Exception: " . $e->getMessage());
            Log::error("Exception Trace: " . $e->getTraceAsString());
            Log::error("=== SMS SEND END (EXCEPTION) ===");
            
            // Fallback to local logging
            $this->logOtpLocally($mobiles, $otp);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback' => 'OTP logged locally due to SMS failure'
            ];
        }
    }

    /**
     * Resend OTP to mobiles number (with 2-minute cooldown)
     */
    public function resendOtp(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'mobiles' => 'required|digits:10|regex:/^[6-9]\d{9}$/'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid mobiles number format. Please enter a 10-digit mobiles number.',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $mobiles = $request->mobiles;

            // Check last OTP sent time (2-minute cooldown)
            $lastOtp = Otp::forMobile($mobiles)
                ->latest()
                ->first();

            if ($lastOtp) {
                $lastOtpTime = Carbon::parse($lastOtp->created_at);
                $timeDifference = now()->diffInSeconds($lastOtpTime);
                $cooldownPeriod = config('otp.resend_cooldown_seconds');

                if ($timeDifference < $cooldownPeriod) {
                    $remainingTime = $cooldownPeriod - $timeDifference;
                    $minutes = floor($remainingTime / 60);
                    $seconds = $remainingTime % 60;

                    return response()->json([
                        'success' => false,
                        'message' => "Please wait before requesting a new OTP. Time remaining: {$minutes}m {$seconds}s",
                        'data' => [
                            'remaining_seconds' => $remainingTime,
                            'can_resend_at' => $lastOtpTime->addSeconds($cooldownPeriod)
                        ],
                        'timestamp' => now()
                    ], 429);
                }
            }

            // Generate new OTP
            $otpLength = config('otp.length');
            $maxOtpValue = (int) str_repeat('9', $otpLength);
            $otp = str_pad(random_int(0, $maxOtpValue), $otpLength, '0', STR_PAD_LEFT);

            // Calculate expiry time
            $otpValidityMinutes = config('otp.validity_minutes');
            $expiresAt = Carbon::now()->addMinutes($otpValidityMinutes);

            // Expire any previous unverified OTPs for this mobiles
            Otp::forMobile($mobiles)
                ->where('is_verified', false)
                ->update(['is_verified' => true]);

            // Store new OTP in database
            Otp::create([
                'mobiles' => $mobiles,
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'is_verified' => false,
            ]);

            // Send OTP via SMS only if SMSMeNow is enabled in .env
            if (config('services.smsmenow.enabled')) {
                SendOtpSms::dispatch($mobiles, $otp);
                Log::info("Resend SMS sent to {$mobiles} via SMSMeNow");
            } else {
                Log::info("SMS disabled (SMSMENOW_ENABLED=false) - Resend OTP for {$mobiles}: {$otp}");
            }

            // Prepare response - include OTP when SMS is disabled
            $responseData = [
                'mobiles' => $mobiles,
                'expires_in' => config('otp.validity_minutes') * 60,
                'resent_at' => now(),
            ];

            if (!config('services.smsmenow.enabled')) {
                $responseData['otp'] = $otp;
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP resent successfully',
                'data' => $responseData,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Resend OTP Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Log OTP locally for development
     */
    private function logOtpLocally($mobiles, $otp)
    {
        if (config('app.env') !== 'local') {
            return; // Never log OTPs outside local development
        }

        $logFile = storage_path('logs/otp_logs.txt');
        file_put_contents($logFile, now() . " - OTP sent for $mobiles\n", FILE_APPEND | LOCK_EX);
        Log::debug("OTP dispatched for mobile: $mobiles");
    }
}
