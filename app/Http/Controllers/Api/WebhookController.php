<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoldTransaction;
use App\Models\User;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    // ─── Signature Helpers ──────────────────────────────────────

    /**
     * Verify HMAC-SHA256 webhook signature.
     * Returns true when verification passes (or when no secret is configured).
     */
    private function verifyHmac(Request $request, string $secretConfigKey, string $headerName = 'X-Webhook-Signature'): bool
    {
        $secret    = config($secretConfigKey, '');
        $signature = $request->header($headerName, '');

        if (!$secret) {
            // No secret configured — accept but log a warning
            Log::warning("Webhook secret not configured for [{$secretConfigKey}]. Skipping signature check.");
            return true;
        }

        if (!$signature) {
            Log::warning("Missing webhook signature header [{$headerName}]");
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * QuickeKYC status webhook
     * POST /api/webhooks/quickekyc-status
     */
    public function quickeKycStatus(Request $request)
    {
        Log::info('QuickeKYC Webhook received', $request->all());

        if (!$this->verifyHmac($request, 'services.quickekyc.webhook_secret', 'X-Webhook-Signature')) {
            Log::warning('QuickeKYC webhook signature mismatch');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $status   = $request->input('status');
        $refId    = $request->input('reference_id');
        $kycType  = $request->input('type'); // pan / aadhaar
        $mobile   = $request->input('mobile');

        if (!$refId || !$status) {
            return response()->json(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        // Update user KYC status in DB (matching core PHP logic)
        if ($mobile) {
            $kycStatus = ($status === 'approved' || $status === 'verified') ? 'verified' : 'rejected';
            $updateData = ['kyc_status' => $kycStatus];

            if ($kycStatus === 'verified') {
                $updateData['kyc_verified_at'] = now();
            }

            User::where('mobile', $mobile)->update($updateData);
            Log::info("User {$mobile} KYC status updated to {$kycStatus} via webhook");
        }

        Log::info("QuickeKYC webhook processed: ref={$refId}, type={$kycType}, status={$status}");

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    /**
     * Augmont user creation webhook
     * POST /api/webhooks/augmont/user-create
     * Sent when user is created in Augmont system
     */
    public function augmontUserCreate(Request $request)
    {
        Log::info('Augmont User Create Webhook', $request->all());

        if (!$this->verifyHmac($request, 'services.augmont.webhook_secret', 'X-Augmont-Signature')) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        // Augmont sends: { "status": "success", "data": { "uniqueId": "...", "userName": "...", ... } }
        $uniqueId = $request->input('data.uniqueId') ?? $request->input('uniqueId');
        $email = $request->input('data.emailId') ?? $request->input('data.email') ?? $request->input('email');
        $mobile = $request->input('data.mobileNumber') ?? $request->input('data.mobile') ?? $request->input('mobile');

        if (!$uniqueId) {
            Log::warning('Augmont User Create Webhook - Missing uniqueId', $request->all());
            return response()->json(['success' => false, 'message' => 'Missing uniqueId'], 400);
        }

        // Try to find and link the user
        $user = null;

        // Try to find by email
        if ($email) {
            $user = User::where('email', $email)->first();
        }

        // Try to find by mobile if not found by email
        if (!$user && $mobile) {
            $user = User::where('mobile', $mobile)->first();
        }

        if ($user) {
            // Link the user to Augmont account
            $user->update(['augmont_user_id' => $uniqueId]);
            Log::info('Augmont user linked via webhook', [
                'local_user_id' => $user->id,
                'augmont_user_id' => $uniqueId,
                'email' => $email
            ]);
        } else {
            Log::warning('Augmont User Create Webhook - User not found', [
                'uniqueId' => $uniqueId,
                'email' => $email,
                'mobile' => $mobile
            ]);
        }

        return response()->json(['success' => true, 'message' => 'User creation webhook processed']);
    }

    /**
     * Augmont FD monthly interest webhook
     * POST /api/webhooks/augmont/fd-monthly-interest
     */
    public function fdMonthlyInterest(Request $request)
    {
        Log::info('Augmont FD Monthly Interest Webhook', $request->all());

        if (!$this->verifyHmac($request, 'services.augmont.webhook_secret', 'X-Augmont-Signature')) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        // Process FD interest credit
        $fdId     = $request->input('fdId');
        $interest = $request->input('interestGold');

        if ($fdId && $interest) {
            Log::info("FD {$fdId} monthly interest credited: {$interest}g");
        }

        return response()->json(['success' => true, 'message' => 'FD interest webhook processed']);
    }

    /**
     * Augmont FD matured webhook
     * POST /api/webhooks/augmont/fd-matured
     */
    public function fdMatured(Request $request)
    {
        Log::info('Augmont FD Matured Webhook', $request->all());

        if (!$this->verifyHmac($request, 'services.augmont.webhook_secret', 'X-Augmont-Signature')) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $fdId = $request->input('fdId');
        if ($fdId) {
            Log::info("FD {$fdId} has matured");
        }

        return response()->json(['success' => true, 'message' => 'FD matured webhook processed']);
    }

    /**
     * Augmont FD closed webhook
     * POST /api/webhooks/augmont/fd-closed
     */
    public function fdClosed(Request $request)
    {
        Log::info('Augmont FD Closed Webhook', $request->all());

        if (!$this->verifyHmac($request, 'services.augmont.webhook_secret', 'X-Augmont-Signature')) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $fdId = $request->input('fdId');
        if ($fdId) {
            Log::info("FD {$fdId} has been closed");
        }

        return response()->json(['success' => true, 'message' => 'FD closed webhook processed']);
    }

    /**
     * Cashfree payment webhook
     * POST /api/webhooks/cashfree
     */
    public function cashfreePayment(Request $request)
    {
        Log::info('Cashfree Payment Webhook received');

        // Verify Cashfree signature
        if (!$this->verifyHmac($request, 'services.cashfree.webhook_secret', 'x-webhook-signature')) {
            Log::warning('Cashfree webhook signature mismatch');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $orderId = $request->input('data.order.order_id');
        $status  = $request->input('data.order.order_status');

        if ($orderId && $status) {
            // Update payment order status
            $paymentOrder = \App\Models\PaymentOrder::where('order_id', $orderId)->first();
            if ($paymentOrder) {
                $paymentOrder->update(['order_status' => $status]);
                Log::info("Payment order {$orderId} updated to {$status}");
            }
        }

        return response()->json(['success' => true, 'message' => 'Payment webhook processed']);
    }

    /**
     * Transaction status update webhook
     * POST /api/webhooks/transaction-status
     */
    public function transactionStatus(Request $request)
    {
        Log::info('Transaction Status Webhook', $request->all());

        $transactionId = $request->input('transactionId');
        $status        = $request->input('status');

        if ($transactionId && $status) {
            GoldTransaction::where('transaction_id', $transactionId)->update([
                'status' => $status,
            ]);
            Log::info("Transaction {$transactionId} status updated to {$status}");
        }

        return response()->json(['success' => true, 'message' => 'Transaction status updated']);
    }
}
