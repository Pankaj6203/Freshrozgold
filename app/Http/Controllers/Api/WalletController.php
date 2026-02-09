<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\UserBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    private array $cashfreeConfig;
    private array $payoutConfig;

    public function __construct()
    {
        $this->cashfreeConfig = config('services.cashfree');
        $this->payoutConfig   = config('services.cashfree_payout');
    }

    // =========================================================
    // GET /api/wallet/balance
    // =========================================================
    public function balance()
    {
        $user   = Auth::user();
        $wallet = $user->getOrCreateWallet();

        return response()->json([
            'success' => true,
            'message' => 'Wallet balance retrieved',
            'data'    => [
                'balance'    => number_format($wallet->balance, 2, '.', ''),
                'currency'   => 'INR',
                'updated_at' => $wallet->updated_at->toDateTimeString(),
            ],
            'timestamp' => now(),
        ]);
    }

    // =========================================================
    // POST /api/wallet/add-money
    // Creates a Cashfree order → frontend opens checkout
    // =========================================================
    public function addMoney(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:500000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
                'timestamp' => now(),
            ], 400);
        }

        try {
            $user   = Auth::user();
            $wallet = $user->getOrCreateWallet();
            $amount = round((float) $request->amount, 2);
            $orderId = 'wallet_' . time() . '_' . rand(10000, 99999);

            // Create Cashfree payment order
            $orderData = [
                'order_id'       => $orderId,
                'order_amount'   => number_format($amount, 2, '.', ''),
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id'    => (string) $user->id,
                    'customer_name'  => $user->name ?? 'Customer',
                    'customer_email' => $user->email ?? 'user@rozgold.in',
                    'customer_phone' => $user->mobile,
                ],
                'order_meta' => [
                    'return_url' => $this->cashfreeConfig['return_url'] . '?order_id=' . $orderId,
                ],
                'order_note' => 'Wallet top-up',
            ];

            $cfResponse = $this->createCashfreeOrder($orderData);

            if (!$cfResponse || !isset($cfResponse['payment_session_id'])) {
                Log::error('Cashfree order creation failed for wallet add money', [
                    'order_id' => $orderId,
                    'response' => $cfResponse,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway error. Please try again.',
                    'timestamp' => now(),
                ], 503);
            }

            // Record pending wallet transaction
            WalletTransaction::create([
                'user_id'        => $user->id,
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'category'       => 'add_money',
                'amount'         => $amount,
                'balance_before' => $wallet->balance,
                'balance_after'  => $wallet->balance, // updated on success
                'reference_id'   => $orderId,
                'payment_method' => 'cashfree',
                'status'         => 'pending',
                'description'    => 'Wallet top-up via Cashfree',
                'meta'           => [
                    'cf_order_id'        => $cfResponse['cf_order_id'] ?? null,
                    'payment_session_id' => $cfResponse['payment_session_id'],
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment order created. Complete payment to add money.',
                'data'    => [
                    'order_id'           => $orderId,
                    'payment_session_id' => $cfResponse['payment_session_id'],
                    'order_amount'       => $amount,
                    'cf_order_id'        => $cfResponse['cf_order_id'] ?? null,
                ],
                'timestamp' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Add money error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now(),
            ], 500);
        }
    }

    // =========================================================
    // POST /api/wallet/verify-payment
    // Called after Cashfree checkout — verifies & credits wallet
    // =========================================================
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'order_id is required',
                'timestamp' => now(),
            ], 400);
        }

        try {
            $orderId = $request->order_id;
            $user    = Auth::user();

            // Find pending transaction
            $txn = WalletTransaction::where('reference_id', $orderId)
                ->where('user_id', $user->id)
                ->where('category', 'add_money')
                ->first();

            if (!$txn) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                    'timestamp' => now(),
                ], 404);
            }

            if ($txn->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already verified',
                    'data'    => ['balance' => number_format($user->getOrCreateWallet()->balance, 2, '.', '')],
                    'timestamp' => now(),
                ]);
            }

            // Verify with Cashfree
            $cfStatus = $this->getCashfreeOrderStatus($orderId);

            if (!$cfStatus || ($cfStatus['order_status'] ?? '') !== 'PAID') {
                $txn->update([
                    'status' => 'failed',
                    'meta'   => array_merge($txn->meta ?? [], ['verification_response' => $cfStatus]),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed. Status: ' . ($cfStatus['order_status'] ?? 'UNKNOWN'),
                    'timestamp' => now(),
                ], 400);
            }

            // Credit wallet inside transaction
            $wallet = $user->getOrCreateWallet();

            DB::transaction(function () use ($wallet, $txn, $cfStatus) {
                $balanceBefore = $wallet->balance;
                $wallet->credit($txn->amount);

                $txn->update([
                    'status'         => 'completed',
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $wallet->balance,
                    'payment_method' => $cfStatus['payment_details']['payment_method'] ?? 'cashfree',
                    'meta'           => array_merge($txn->meta ?? [], [
                        'payment_details' => $cfStatus['payment_details'] ?? null,
                    ]),
                ]);
            });

            Log::info('Wallet credited via add money', [
                'user_id'  => $user->id,
                'amount'   => $txn->amount,
                'order_id' => $orderId,
            ]);

            return response()->json([
                'success' => true,
                'message' => "₹{$txn->amount} added to wallet successfully",
                'data'    => [
                    'balance'  => number_format($wallet->fresh()->balance, 2, '.', ''),
                    'amount'   => number_format($txn->amount, 2, '.', ''),
                    'order_id' => $orderId,
                ],
                'timestamp' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Verify payment error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now(),
            ], 500);
        }
    }

    // =========================================================
    // POST /api/wallet/withdraw
    // Debit wallet → Cashfree Payout to user's bank
    // =========================================================
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'  => 'required|numeric|min:1|max:200000',
            'bank_id' => 'required|integer|exists:user_banks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
                'timestamp' => now(),
            ], 400);
        }

        try {
            $user   = Auth::user();
            $wallet = $user->getOrCreateWallet();
            $amount = round((float) $request->amount, 2);

            // Verify bank belongs to user
            $bank = UserBank::where('id', $request->bank_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found or inactive',
                    'timestamp' => now(),
                ], 404);
            }

            // Check balance
            if (!$wallet->hasSufficientBalance($amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance. Current balance: ₹' . number_format($wallet->balance, 2),
                    'timestamp' => now(),
                ], 400);
            }

            $transferId = 'wd_' . time() . '_' . rand(10000, 99999);

            // Debit wallet + record transaction atomically
            $txn = DB::transaction(function () use ($wallet, $user, $amount, $transferId, $bank) {
                $balanceBefore = $wallet->balance;
                $wallet->debit($amount);

                return WalletTransaction::create([
                    'user_id'        => $user->id,
                    'wallet_id'      => $wallet->id,
                    'type'           => 'debit',
                    'category'       => 'withdrawal',
                    'amount'         => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $wallet->balance,
                    'reference_id'   => $transferId,
                    'payment_method' => 'bank_transfer',
                    'status'         => 'processing',
                    'description'    => "Withdrawal to {$bank->bank_name} ****" . substr($bank->account_number, -4),
                    'meta'           => [
                        'bank_id'        => $bank->id,
                        'account_number' => $bank->account_number,
                        'ifsc_code'      => $bank->ifsc_code,
                        'account_name'   => $bank->account_name,
                    ],
                ]);
            });

            // Initiate Cashfree Payout
            $payoutResult = $this->initiateCashfreePayout([
                'transfer_id'    => $transferId,
                'amount'         => $amount,
                'account_number' => $bank->account_number,
                'ifsc_code'      => $bank->ifsc_code,
                'account_name'   => $bank->account_name,
                'phone'          => $user->mobile,
                'email'          => $user->email ?? 'user@rozgold.in',
            ]);

            if ($payoutResult['success']) {
                $txn->update([
                    'status' => 'processing',
                    'meta'   => array_merge($txn->meta ?? [], ['payout_response' => $payoutResult['data'] ?? null]),
                ]);

                Log::info('Withdrawal initiated', [
                    'user_id'     => $user->id,
                    'transfer_id' => $transferId,
                    'amount'      => $amount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Withdrawal of ₹{$amount} initiated. Amount will be credited to your bank within 24 hours.",
                    'data'    => [
                        'transfer_id' => $transferId,
                        'amount'      => number_format($amount, 2, '.', ''),
                        'balance'     => number_format($wallet->fresh()->balance, 2, '.', ''),
                        'bank'        => $bank->bank_name . ' ****' . substr($bank->account_number, -4),
                        'status'      => 'processing',
                    ],
                    'timestamp' => now(),
                ]);
            } else {
                // Payout failed — refund wallet
                DB::transaction(function () use ($wallet, $txn, $payoutResult) {
                    $wallet->credit($txn->amount);
                    $txn->update([
                        'status'       => 'failed',
                        'balance_after' => $wallet->balance,
                        'meta'         => array_merge($txn->meta ?? [], ['payout_error' => $payoutResult['message'] ?? 'Payout API failed']),
                    ]);
                });

                Log::error('Cashfree payout failed — wallet refunded', [
                    'user_id'     => $user->id,
                    'transfer_id' => $transferId,
                    'error'       => $payoutResult['message'] ?? 'Unknown',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal failed. Amount has been refunded to your wallet.',
                    'data'    => ['balance' => number_format($wallet->fresh()->balance, 2, '.', '')],
                    'timestamp' => now(),
                ], 503);
            }

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => now(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Withdraw error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now(),
            ], 500);
        }
    }

    // =========================================================
    // GET /api/wallet/transactions
    // =========================================================
    public function transactions(Request $request)
    {
        $user    = Auth::user();
        $wallet  = $user->getOrCreateWallet();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Optional filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $txns = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Wallet transactions retrieved',
            'data'    => [
                'balance'      => number_format($wallet->balance, 2, '.', ''),
                'transactions' => $txns->items(),
                'pagination'   => [
                    'current_page' => $txns->currentPage(),
                    'last_page'    => $txns->lastPage(),
                    'per_page'     => $txns->perPage(),
                    'total'        => $txns->total(),
                ],
            ],
            'timestamp' => now(),
        ]);
    }

    // =========================================================
    // Private: Cashfree Order Creation (reuses existing pattern)
    // =========================================================
    private function createCashfreeOrder(array $orderData): ?array
    {
        $url = $this->cashfreeConfig['api_url'] . '/orders';

        $headers = [
            'Content-Type: application/json',
            'x-api-version: ' . $this->cashfreeConfig['api_version'],
            'x-client-id: ' . $this->cashfreeConfig['app_id'],
            'x-client-secret: ' . $this->cashfreeConfig['secret_key'],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($orderData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('services.cashfree.ssl_verify', true),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('Cashfree cURL error', ['error' => $error]);
            return null;
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            Log::error('Cashfree order API error', ['http_code' => $httpCode, 'response' => $result]);
            return null;
        }

        return $result;
    }

    // =========================================================
    // Private: Cashfree Order Status Check
    // =========================================================
    private function getCashfreeOrderStatus(string $orderId): ?array
    {
        $url = $this->cashfreeConfig['api_url'] . '/orders/' . $orderId;

        $headers = [
            'Content-Type: application/json',
            'x-api-version: ' . $this->cashfreeConfig['api_version'],
            'x-client-id: ' . $this->cashfreeConfig['app_id'],
            'x-client-secret: ' . $this->cashfreeConfig['secret_key'],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('services.cashfree.ssl_verify', true),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            return ['order_status' => 'FAILED', 'message' => $result['message'] ?? 'Status check failed'];
        }

        return $result;
    }

    // =========================================================
    // Private: Cashfree Payout (bank transfer to user)
    // =========================================================
    private function initiateCashfreePayout(array $data): array
    {
        $url = $this->payoutConfig['api_url'] . '/transfers';

        // Cashfree Payout auth
        $headers = [
            'Content-Type: application/json',
            'x-client-id: ' . $this->payoutConfig['client_id'],
            'x-client-secret: ' . $this->payoutConfig['client_secret'],
            'x-api-version: 2024-01-01',
        ];

        $payload = [
            'transfer_id'    => $data['transfer_id'],
            'transfer_amount' => $data['amount'],
            'transfer_currency' => 'INR',
            'transfer_mode'  => 'banktransfer',
            'beneficiary_details' => [
                'beneficiary_id'             => 'bene_' . md5($data['account_number'] . $data['ifsc_code']),
                'beneficiary_name'           => $data['account_name'],
                'beneficiary_account_number' => $data['account_number'],
                'beneficiary_ifsc'           => $data['ifsc_code'],
                'beneficiary_phone'          => $data['phone'],
                'beneficiary_email'          => $data['email'],
            ],
        ];

        Log::info('Initiating Cashfree payout', [
            'transfer_id' => $data['transfer_id'],
            'amount'       => $data['amount'],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('services.cashfree.ssl_verify', true),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('Cashfree Payout cURL error', ['error' => $error]);
            return ['success' => false, 'message' => 'Payout connection error'];
        }

        $result = json_decode($response, true);

        Log::info('Cashfree Payout response', [
            'http_code' => $httpCode,
            'response'  => $result,
        ]);

        if ($httpCode >= 200 && $httpCode < 300 && isset($result['transfer_id'])) {
            return ['success' => true, 'data' => $result];
        }

        return [
            'success' => false,
            'message' => $result['message'] ?? $result['subCode'] ?? 'Payout API error (HTTP ' . $httpCode . ')',
            'data'    => $result,
        ];
    }
}
