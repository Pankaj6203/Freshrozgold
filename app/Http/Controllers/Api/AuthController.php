<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Merchant login (Augmont auth)
     * POST /api/auth/merchant-login
     */
    public function merchantLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => $user->only(['id', 'name', 'email', 'mobile']),
            ],
        ]);
    }

    /**
     * Register a new user
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|min:2|max:50',
            'email'    => 'required|email|unique:users,email',
            'mobile'   => 'required|digits:10|unique:users,mobile',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'mobile'   => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => $user->only(['id', 'name', 'email', 'mobile']),
            ],
        ], 201);
    }

    /**
     * Send OTP to email
     * POST /api/auth/send-email-otp
     */
    public function sendEmailOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate old OTPs
        EmailOtp::where('email', $request->email)->update(['used' => true]);

        EmailOtp::create([
            'email'      => $request->email,
            'otp'        => Hash::make($otp),
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send via email
        try {
            Mail::raw("Your verification OTP is: {$otp}. Valid for 10 minutes.", function ($message) use ($request) {
                $message->to($request->email)->subject('Email Verification OTP - FreshRozGold');
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send OTP email'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to email',
        ]);
    }

    /**
     * Verify email OTP
     * POST /api/auth/verify-email-otp
     */
    public function verifyEmailOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $emailOtp = EmailOtp::where('email', $request->email)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$emailOtp || !Hash::check($request->otp, $emailOtp->otp)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
        }

        $emailOtp->update(['used' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
        ]);
    }

    /**
     * Logout — revoke current token
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
