<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\AugmontService;
use App\Models\GoldRate;
use App\Models\GoldTransaction;
use App\Http\Requests\BuyGoldRequest;
use App\Http\Requests\SellGoldRequest;

class GoldController extends Controller
{
    public function __construct(private AugmontService $augmont) {}



    /**
     * Get current and historical gold rates
     */
    public function getRates(Request $request)
    {
        try {
            Log::info("Fetching gold rates from Augmont API");

            // Check if force refresh is requested
            $forceRefresh = $request->query('refresh') === 'true';

            if ($forceRefresh) {
                // Clear cache and force fresh API call
                Cache::forget('gold_rates');
                Log::info("Force refresh requested - clearing cache");
            }

            // Cache rates for 15 seconds (reduced from 60 for more real-time updates)
            $currentRates = Cache::remember('gold_rates', 15, function () {
                $ratesResponse = $this->augmont->getRates();

                if (!$ratesResponse['success']) {
                    throw new \Exception('Augmont API error: ' . ($ratesResponse['message'] ?? 'Connection failed'));
                }

                $responseData = $ratesResponse['data'];

                // Handle different response structures
                $rates = null;
                $blockId = null;

                if (isset($responseData['result']['data']['rates'])) {
                    $rates = $responseData['result']['data']['rates'];
                    $blockId = $responseData['result']['data']['blockId'] ?? null;
                } elseif (isset($responseData['data']['rates'])) {
                    $rates = $responseData['data']['rates'];
                    $blockId = $responseData['data']['blockId'] ?? null;
                } elseif (isset($responseData['rates'])) {
                    $rates = $responseData['rates'];
                    $blockId = $responseData['blockId'] ?? null;
                }

                if (!$rates) {
                    throw new \Exception('Invalid response structure from Augmont API');
                }

                $currentRates = [
                    'buy_price' => number_format(floatval($rates['gBuy'] ?? $rates['buy'] ?? 0), 2, '.', ''),
                    'sell_price' => number_format(floatval($rates['gSell'] ?? $rates['sell'] ?? 0), 2, '.', ''),
                    'source' => 'augmont',
                    'updated_at' => now()->toDateTimeString(),
                    'block_id' => $blockId
                ];

                // Store in database
                GoldRate::create([
                    'buy_rate' => $currentRates['buy_price'],
                    'sell_rate' => $currentRates['sell_price'],
                    'source' => 'augmont',
                    'block_id' => $blockId
                ]);

                Log::info("Successfully fetched Augmont rates", ['rates' => $currentRates]);

                return $currentRates;
            });

            return response()->json([
                'success' => true,
                'message' => 'Gold rates retrieved successfully from Augmont',
                'data' => [
                    'current' => $currentRates
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Get gold rates error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch gold rates',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get buy invoice
     */
    public function getBuyInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|string',
                'amount' => 'nullable|numeric|min:0',
                'grams' => 'nullable|numeric|min:0',
                'rate' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $transactionId = $request->transaction_id;
            $grams = $request->grams;
            $amount = $request->amount;
            $rate = $request->rate;

            // If rate not provided, get current rate
            if (!$rate) {
                $ratesResponse = $this->augmont->getRates();
                if ($ratesResponse['success'] && isset($ratesResponse['data']['result']['data']['rates'])) {
                    $rate = $ratesResponse['data']['result']['data']['rates']['gBuy'];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to fetch current gold rates',
                        'timestamp' => now()
                    ], 503);
                }
            }

            // Calculate values if not provided
            if (!$grams && $amount) {
                $grams = $amount / $rate;
            } elseif (!$amount && $grams) {
                $amount = $grams * $rate;
            }

            Log::info("Generating buy invoice", [
                'transaction_id' => $transactionId,
                'grams' => $grams,
                'rate' => $rate,
                'amount' => $amount
            ]);

            // Calculate GST (3% = 1.5% CGST + 1.5% SGST)
            $gstAmount = $amount * 0.03;
            $totalAmount = $amount + $gstAmount;

            // Create invoice response
            $invoice = [
                'invoice_id' => 'INV_' . time(),
                'transaction_id' => $transactionId,
                'invoice_number' => 'RG/BUY/' . date('Y') . '/' . rand(10000, 99999),
                'invoice_date' => date('Y-m-d'),
                'transaction_type' => 'buy',
                'transaction_details' => [
                    'quantity' => number_format($grams, 4),
                    'unit' => 'grams',
                    'rate' => number_format($rate, 2),
                    'amount' => number_format($amount, 2),
                    'cgst' => number_format($gstAmount / 2, 2),
                    'sgst' => number_format($gstAmount / 2, 2),
                    'gst_total' => number_format($gstAmount, 2),
                    'total_amount' => number_format($totalAmount, 2)
                ],
                'source' => 'augmont',
                'rate_source' => 'Augmont UAT API',
                'status' => 'generated',
                'created_at' => now()->toDateTimeString()
            ];

            // Store in database
            $this->storeTransaction([
                'transaction_id' => $transactionId,
                'invoice_id' => $invoice['invoice_id'],
                'invoice_number' => $invoice['invoice_number'],
                'transaction_type' => 'buy',
                'quantity' => $grams,
                'rate' => $rate,
                'amount' => $amount,
                'tax_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'status' => 'completed',
                'invoice_data' => $invoice
            ], 'buy invoice');

            return response()->json([
                'success' => true,
                'message' => 'Buy invoice generated successfully',
                'data' => [
                    'invoice' => $invoice
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Get buy invoice error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate buy invoice',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get sell invoice
     */
    public function getSellInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|string',
                'amount' => 'nullable|numeric|min:0',
                'grams' => 'nullable|numeric|min:0',
                'rate' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $transactionId = $request->transaction_id;
            $grams = $request->grams;
            $amount = $request->amount;
            $rate = $request->rate;

            // If rate not provided, get current sell rate
            if (!$rate) {
                $ratesResponse = $this->augmont->getRates();
                if ($ratesResponse['success'] && isset($ratesResponse['data']['result']['data']['rates'])) {
                    $rate = $ratesResponse['data']['result']['data']['rates']['gSell'];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to fetch current gold rates',
                        'timestamp' => now()
                    ], 503);
                }
            }

            // Calculate values if not provided
            if (!$grams && $amount) {
                $grams = $amount / $rate;
            } elseif (!$amount && $grams) {
                $amount = $grams * $rate;
            }

            Log::info("Generating sell invoice", [
                'transaction_id' => $transactionId,
                'grams' => $grams,
                'rate' => $rate,
                'amount' => $amount
            ]);

            // Calculate TDS (1% on gold sale)
            $tdsAmount = $amount * 0.01;
            $netAmount = $amount - $tdsAmount;

            // Create invoice response
            $invoice = [
                'invoice_id' => 'INV_' . time(),
                'transaction_id' => $transactionId,
                'invoice_number' => 'RG/SELL/' . date('Y') . '/' . rand(10000, 99999),
                'invoice_date' => date('Y-m-d'),
                'transaction_type' => 'sell',
                'transaction_details' => [
                    'quantity' => number_format($grams, 4),
                    'unit' => 'grams',
                    'rate' => number_format($rate, 2),
                    'amount' => number_format($amount, 2),
                    'tds' => number_format($tdsAmount, 2),
                    'tds_percentage' => '1%',
                    'net_amount' => number_format($netAmount, 2)
                ],
                'source' => 'augmont',
                'rate_source' => 'Augmont UAT API',
                'status' => 'generated',
                'created_at' => now()->toDateTimeString()
            ];

            // Store in database
            $this->storeTransaction([
                'transaction_id' => $transactionId,
                'invoice_id' => $invoice['invoice_id'],
                'invoice_number' => $invoice['invoice_number'],
                'transaction_type' => 'sell',
                'quantity' => $grams,
                'rate' => $rate,
                'amount' => $amount,
                'tax_amount' => $tdsAmount,
                'total_amount' => $netAmount,
                'status' => 'completed',
                'invoice_data' => $invoice
            ], 'sell invoice');

            return response()->json([
                'success' => true,
                'message' => 'Sell invoice generated successfully',
                'data' => [
                    'invoice' => $invoice
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Get sell invoice error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate sell invoice',
                'timestamp' => now()
            ], 500);
        }
    }



    /**
     * Store transaction in database (centralised)
     */
    private function storeTransaction(array $data, string $context = 'transaction')
    {
        try {
            DB::transaction(function () use ($data) {
                GoldTransaction::create($data);
            });
            Log::info("Gold {$context} stored", ['transaction_id' => $data['transaction_id'] ?? 'unknown']);
        } catch (\Exception $e) {
            Log::critical("CRITICAL: Gold {$context} DB record failed", [
                'transaction_id' => $data['transaction_id'] ?? 'unknown',
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Buy gold with Augmont integration
     * Returns error if Augmont API fails - NO fake transactions when real money is involved
     */
    public function buyGold(BuyGoldRequest $request)
    {
        try {

            // Auto-generate merchantTransactionId if not provided
            $merchantTransactionId = $request->merchantTransactionId ?: 'TXN_' . now()->format('YmdHis') . '_' . mt_rand(1000, 9999);
            $request->merge(['merchantTransactionId' => $merchantTransactionId]);

            // Get uniqueId from request or authenticated user's augmont_user_id
            $uniqueId = $request->uniqueId;
            if (!$uniqueId && Auth::check()) {
                $user = Auth::user();
                $uniqueId = $user->augmont_user_id;
            }

            if (!$uniqueId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Augmont user ID is required. Your account is not yet linked to Augmont.',
                    'errors' => [
                        'uniqueId' => ['The unique id field is required.']
                    ],
                    'help' => [
                        'step1' => 'Create an Augmont user account first',
                        'step2' => 'POST /api/augmont/users with your uniqueId and details',
                        'after_creation' => 'Your account will be automatically linked for gold transactions'
                    ],
                    'timestamp' => now()
                ], 400);
            }

            // Get current rate if not provided
            $lockPrice = $request->lockPrice;
            $blockId = $request->blockId;

            if (!$lockPrice || !$blockId) {
                $ratesResponse = $this->augmont->getRates();
                if ($ratesResponse['success'] && isset($ratesResponse['data']['result']['data']['rates'])) {
                    $lockPrice = $lockPrice ?? $ratesResponse['data']['result']['data']['rates']['gBuy'];
                    $blockId = $blockId ?? $ratesResponse['data']['result']['data']['blockId'];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to fetch current gold rates',
                        'timestamp' => now()
                    ], 503);
                }
            }

            // Calculate quantity or amount
            $quantity = $request->quantity;
            $amount = $request->amount;

            if (!$quantity && !$amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either quantity or amount is required',
                    'timestamp' => now()
                ], 400);
            }

            if (!$quantity) {
                // Augmont requires max 4 decimal places
                    // Augmont requires max 4 decimal places
                $quantity = round($amount / $lockPrice, 4);
            } elseif (!$amount) {
                $amount = $quantity * $lockPrice;
            }

            $gstAmount = $amount * 0.03;
            $totalAmount = $amount + $gstAmount;

            // Prepare data for Augmont API
            $buyData = [
                'lockPrice' => $lockPrice,
                'metalType' => $request->metalType,
                'merchantTransactionId' => $request->merchantTransactionId,
                'uniqueId' => $uniqueId,
                'blockId' => $blockId,
                'modeOfPayment' => $request->modeOfPayment ?? 'Online',
                'quantity' => $quantity
            ];

            // Add optional fields
            if ($request->has('referenceType')) $buyData['referenceType'] = $request->referenceType;
            if ($request->has('referenceId')) $buyData['referenceId'] = $request->referenceId;
            if ($request->has('utmSource')) $buyData['utmSource'] = $request->utmSource;
            if ($request->has('utmMedium')) $buyData['utmMedium'] = $request->utmMedium;
            if ($request->has('utmCampaign')) $buyData['utmCampaign'] = $request->utmCampaign;

            Log::info('Attempting Augmont Buy API', ['data' => $buyData]);

            // Call Augmont API - NO FALLBACK, must succeed
            $response = $this->augmont->buyGold($buyData);

            if (!$response['success']) {
                // Augmont failed - return error immediately
                Log::error('Augmont buy transaction FAILED', [
                    'response' => $response,
                    'data_sent' => $buyData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to purchase gold from Augmont: ' . ($response['message'] ?? 'Unknown error'),
                    'error_details' => [
                        'augmont_status' => $response['status_code'] ?? 'unknown',
                        'augmont_message' => $response['message'] ?? 'No message',
                        'augmont_data' => $response['data'] ?? null
                    ],
                    'suggestion' => 'Please contact support. Payment should be refunded if charged.',
                    'timestamp' => now()
                ], 503);
            }

            // Augmont succeeded!
            Log::info('Augmont buy transaction successful', ['response' => $response['data']]);

            // Determine User ID
            $userId = null;
            if (Auth::check()) {
                $userId = Auth::id();
            } elseif ($uniqueId && is_numeric($uniqueId)) {
                $userId = $uniqueId;
            }

            // Create transaction record - only 'completed' status since Augmont succeeded
            $user = Auth::check() ? Auth::user() : null;

            $this->storeTransaction([
                'user_id' => $userId,
                'unique_id' => $uniqueId,
                'transaction_id' => $request->merchantTransactionId,
                'merchant_transaction_id' => $request->merchantTransactionId,
                'transaction_type' => 'buy',
                'metal_type' => $request->metalType,
                'quantity' => $quantity,
                'rate' => $lockPrice,
                'amount' => $amount,
                'tax_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'block_id' => $blockId,
                'mode_of_payment' => $request->modeOfPayment ?? 'Online',
                'customer_name' => $user?->name,
                'customer_email' => $user?->email,
                'customer_mobile' => $user?->mobile ?? $user?->phone,
                'payment_method' => $request->modeOfPayment ?? 'Online',
                'payment_status' => 'paid',
                'status' => 'completed',
                'source' => 'augmont',
                'augmont_response' => json_encode($response['data']),
                'reference_type' => $request->referenceType ?? null,
                'reference_id' => $request->referenceId ?? null,
                'invoice_data' => $response['data'],
            ], 'Augmont buy');

            // Prepare response - only real Augmont transactions
            $responseData = [
                'transaction' => [
                    'transaction_id' => $request->merchantTransactionId,
                    'unique_id' => $uniqueId,
                    'metal_type' => $request->metalType,
                    'quantity' => round($quantity, 4),
                    'rate' => round($lockPrice, 2),
                    'amount' => round($amount, 2),
                    'gst' => round($gstAmount, 2),
                    'total_amount' => round($totalAmount, 2),
                    'block_id' => $blockId,
                    'status' => 'completed',
                    'source' => 'augmont',
                    'mode_of_payment' => $request->modeOfPayment ?? 'Online',
                    'payment_status' => 'paid',
                    'created_at' => now()->toDateTimeString()
                ],
                'augmont_data' => $response['data']
            ];

            return response()->json([
                'success' => true,
                'message' => 'Gold purchase completed successfully via Augmont',
                'data' => $responseData,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Buy gold error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing buy request',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Sell gold with Augmont integration
     * Returns error if Augmont API fails - NO fake transactions
     */
    public function sellGold(SellGoldRequest $request)
    {
        try {

            // Get uniqueId from request or authenticated user's augmont_user_id
            $uniqueId = $request->uniqueId;
            if (!$uniqueId && Auth::check()) {
                $user = Auth::user();
                $uniqueId = $user->augmont_user_id;
            }

            if (!$uniqueId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Augmont user ID is required. Your account is not yet linked to Augmont.',
                    'errors' => [
                        'uniqueId' => ['The unique id field is required.']
                    ],
                    'help' => [
                        'step1' => 'Create an Augmont user account first',
                        'step2' => 'POST /api/augmont/users with your uniqueId and details',
                        'after_creation' => 'Your account will be automatically linked for gold transactions'
                    ],
                    'timestamp' => now()
                ], 400);
            }

            // Get current rate if not provided
            $lockPrice = $request->lockPrice;
            $blockId = $request->blockId;

            if (!$lockPrice || !$blockId) {
                $ratesResponse = $this->augmont->getRates();
                if ($ratesResponse['success'] && isset($ratesResponse['data']['result']['data']['rates'])) {
                    $lockPrice = $lockPrice ?? $ratesResponse['data']['result']['data']['rates']['gSell'];
                    $blockId = $blockId ?? $ratesResponse['data']['result']['data']['blockId'];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to fetch current gold rates',
                        'timestamp' => now()
                    ], 503);
                }
            }

            $quantity = $request->quantity;
            $amount = $quantity * $lockPrice;

            // Calculate TDS (1%)
            $tdsAmount = $amount * 0.01;
            $netAmount = $amount - $tdsAmount;

            // Prepare data for Augmont API
            $sellData = [
                'lockPrice' => $lockPrice,
                'metalType' => $request->metalType,
                'merchantTransactionId' => $request->merchantTransactionId,
                'uniqueId' => $uniqueId,
                'blockId' => $blockId,
                'quantity' => $quantity,
            ];

            // Add bank details if provided (resolve userBankId to bank object if needed)
            if ($request->has('userBank')) {
                $sellData['userBank'] = $request->userBank;
            } elseif ($request->has('userBankId') && Auth::check()) {
                $bankRecord = \App\Models\UserBank::where('id', $request->userBankId)
                    ->where('user_id', Auth::id())
                    ->first();
                if ($bankRecord) {
                    $sellData['userBank'] = [
                        'accountNumber' => $bankRecord->account_number,
                        'accountName'   => $bankRecord->account_name,
                        'ifscCode'      => $bankRecord->ifsc_code,
                    ];
                    Log::info('Resolved userBankId to bank details', ['bank_id' => $request->userBankId]);
                }
            }

            Log::info('Attempting Augmont Sell API', ['data' => $sellData]);

            // Call Augmont API - NO FALLBACK, must succeed
            $response = $this->augmont->sellGold($sellData);

            if (!$response['success']) {
                // Augmont failed - return error immediately
                Log::error('Augmont sell transaction FAILED', [
                    'response' => $response,
                    'data_sent' => $sellData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to sell gold via Augmont: ' . ($response['message'] ?? 'Unknown error'),
                    'error_details' => [
                        'augmont_status' => $response['status_code'] ?? 'unknown',
                        'augmont_message' => $response['message'] ?? 'No message',
                        'augmont_data' => $response['data'] ?? null
                    ],
                    'suggestion' => 'Please contact support for assistance.',
                    'timestamp' => now()
                ], 503);
            }

            // Augmont succeeded!
            Log::info('Augmont sell transaction successful', ['response' => $response['data']]);
            $userId = null;
            if (Auth::check()) {
                $userId = Auth::id();
            } elseif ($uniqueId && is_numeric($uniqueId)) {
                $userId = $uniqueId;
            }

            // Create transaction record - only 'completed' status since Augmont succeeded
            $user = Auth::check() ? Auth::user() : null;

            $this->storeTransaction([
                'user_id' => $userId,
                'unique_id' => $uniqueId,
                'transaction_id' => $request->merchantTransactionId,
                'merchant_transaction_id' => $request->merchantTransactionId,
                'transaction_type' => 'sell',
                'metal_type' => $request->metalType,
                'quantity' => $quantity,
                'rate' => $lockPrice,
                'amount' => $amount,
                'tax_amount' => $tdsAmount,
                'total_amount' => $netAmount,
                'block_id' => $blockId,
                'mode_of_payment' => 'Bank Transfer',
                'customer_name' => $user?->name,
                'customer_email' => $user?->email,
                'customer_mobile' => $user?->mobile ?? $user?->phone,
                'payment_method' => 'Bank Transfer',
                'payment_status' => 'pending',
                'status' => 'completed',
                'source' => 'augmont',
                'augmont_response' => json_encode($response['data']),
                'invoice_data' => array_merge(
                    $response['data'] ?? [],
                    $request->has('userBank') ? ['bank_details' => $request->userBank] : []
                ),
            ], 'Augmont sell');

            // Prepare response - only real Augmont transactions
            $responseData = [
                'transaction' => [
                    'transaction_id' => $request->merchantTransactionId,
                    'unique_id' => $uniqueId,
                    'metal_type' => $request->metalType,
                    'quantity' => round($quantity, 4),
                    'rate' => round($lockPrice, 2),
                    'amount' => round($amount, 2),
                    'tds' => round($tdsAmount, 2),
                    'net_amount' => round($netAmount, 2),
                    'block_id' => $blockId,
                    'status' => 'completed',
                    'source' => 'augmont',
                    'payment_status' => 'pending',
                    'created_at' => now()->toDateTimeString()
                ],
                'augmont_data' => $response['data']
            ];

            if ($request->has('userBank')) {
                $responseData['transaction']['bank_details'] = $request->userBank;
            }

            return response()->json([
                'success' => true,
                'message' => 'Gold sell completed successfully via Augmont',
                'data' => $responseData,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Sell gold error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing sell request',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get buy transaction info
     */
    public function getBuyInfo(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            // Get transaction from database
            $transaction = GoldTransaction::where('transaction_id', $request->transaction_id)
                ->where('transaction_type', 'buy')
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buy transaction not found',
                    'timestamp' => now()
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Buy transaction info retrieved successfully',
                'data' => [
                    'transaction' => [
                        'transaction_id' => $transaction->transaction_id,
                        'merchant_transaction_id' => $transaction->merchant_transaction_id,
                        'invoice_id' => $transaction->invoice_id,
                        'invoice_number' => $transaction->invoice_number,
                        'transaction_type' => $transaction->transaction_type,
                        'metal_type' => $transaction->metal_type,
                        'quantity' => round(floatval($transaction->quantity ?? 0), 4),
                        'rate' => round(floatval($transaction->rate ?? 0), 2),
                        'amount' => round(floatval($transaction->amount ?? 0), 2),
                        'tax_amount' => round(floatval($transaction->tax_amount ?? 0), 2),
                        'total_amount' => round(floatval($transaction->total_amount ?? 0), 2),
                        'status' => $transaction->status,
                        'payment_status' => $transaction->payment_status,
                        'source' => $transaction->source,
                        'created_at' => $transaction->created_at
                    ]
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get buy info error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching buy transaction info',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get sell transaction info
     */
    public function getSellInfo(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            // Get transaction from database
            $transaction = GoldTransaction::where('transaction_id', $request->transaction_id)
                ->where('transaction_type', 'sell')
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sell transaction not found',
                    'timestamp' => now()
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sell transaction info retrieved successfully',
                'data' => [
                    'transaction' => [
                        'transaction_id' => $transaction->transaction_id,
                        'merchant_transaction_id' => $transaction->merchant_transaction_id,
                        'invoice_id' => $transaction->invoice_id,
                        'invoice_number' => $transaction->invoice_number,
                        'transaction_type' => $transaction->transaction_type,
                        'metal_type' => $transaction->metal_type,
                        'quantity' => round(floatval($transaction->quantity ?? 0), 4),
                        'rate' => round(floatval($transaction->rate ?? 0), 2),
                        'amount' => round(floatval($transaction->amount ?? 0), 2),
                        'tax_amount' => round(floatval($transaction->tax_amount ?? 0), 2),
                        'total_amount' => round(floatval($transaction->total_amount ?? 0), 2),
                        'status' => $transaction->status,
                        'payment_status' => $transaction->payment_status,
                        'source' => $transaction->source,
                        'created_at' => $transaction->created_at
                    ]
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get sell info error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching sell transaction info',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get list of buy transactions
     */
    public function getBuyList(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $transactions = GoldTransaction::where('user_id', Auth::id())
                ->where('transaction_type', 'buy')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Buy transactions list retrieved successfully',
                'data' => [
                    'transactions' => collect($transactions->items())->map(function($transaction) {
                        return [
                            'transaction_id' => $transaction->transaction_id,
                            'merchant_transaction_id' => $transaction->merchant_transaction_id,
                            'invoice_id' => $transaction->invoice_id,
                            'invoice_number' => $transaction->invoice_number,
                            'metal_type' => $transaction->metal_type,
                            'quantity' => round(floatval($transaction->quantity ?? 0), 4),
                            'rate' => round(floatval($transaction->rate ?? 0), 2),
                            'amount' => round(floatval($transaction->amount ?? 0), 2),
                            'total_amount' => round(floatval($transaction->total_amount ?? 0), 2),
                            'status' => $transaction->status,
                            'payment_status' => $transaction->payment_status,
                            'created_at' => $transaction->created_at
                        ];
                    })->values(),
                    'pagination' => [
                        'total' => $transactions->total(),
                        'per_page' => $transactions->perPage(),
                        'current_page' => $transactions->currentPage(),
                        'last_page' => $transactions->lastPage(),
                        'from' => $transactions->firstItem(),
                        'to' => $transactions->lastItem()
                    ]
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get buy list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching buy transactions list',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get list of sell transactions
     */
    public function getSellList(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $transactions = GoldTransaction::where('user_id', Auth::id())
                ->where('transaction_type', 'sell')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Sell transactions list retrieved successfully',
                'data' => [
                    'transactions' => collect($transactions->items())->map(function($transaction) {
                        return [
                            'transaction_id' => $transaction->transaction_id,
                            'merchant_transaction_id' => $transaction->merchant_transaction_id,
                            'invoice_id' => $transaction->invoice_id,
                            'invoice_number' => $transaction->invoice_number,
                            'metal_type' => $transaction->metal_type,
                            'quantity' => round(floatval($transaction->quantity ?? 0), 4),
                            'rate' => round(floatval($transaction->rate ?? 0), 2),
                            'amount' => round(floatval($transaction->amount ?? 0), 2),
                            'total_amount' => round(floatval($transaction->total_amount ?? 0), 2),
                            'status' => $transaction->status,
                            'payment_status' => $transaction->payment_status,
                            'created_at' => $transaction->created_at
                        ];
                    })->values(),
                    'pagination' => [
                        'total' => $transactions->total(),
                        'per_page' => $transactions->perPage(),
                        'current_page' => $transactions->currentPage(),
                        'last_page' => $transactions->lastPage(),
                        'from' => $transactions->firstItem(),
                        'to' => $transactions->lastItem()
                    ]
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get sell list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching sell transactions list',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Update gold transaction status after payment completion
     * POST /api/gold/update-transaction-status
     */
    public function updateTransactionStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_transaction_id' => 'required|string',
            'status'                 => 'required|string|in:pending,completed,failed,cancelled',
            'payment_status'         => 'required|string|in:pending,paid,failed,refunded',
            'payment_method'         => 'nullable|string|max:50',
            'payment_reference'      => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        try {
            $user = $request->user();
            $transaction = GoldTransaction::where('merchant_transaction_id', $request->merchant_transaction_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            $transaction->update([
                'status'            => $request->status,
                'payment_status'    => $request->payment_status,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->payment_reference,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction status updated successfully',
                'data'    => [
                    'merchant_transaction_id' => $request->merchant_transaction_id,
                    'status'                 => $request->status,
                    'payment_status'         => $request->payment_status,
                    'updated_at'             => now()->toDateTimeString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Update transaction status error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}
