<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentOrder;

class PaymentController extends Controller
{
    private $cashfreeConfig;

    public function __construct()
    {
        $this->cashfreeConfig = config('services.cashfree');
    }

    /**
     * Create Cashfree payment order
     */
    public function createOrder(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'order_amount' => 'required|numeric|min:1',
                'customer_details' => 'required|array',
                'customer_details.customer_id' => 'required|string',
                'customer_details.customer_email' => 'required|email',
                'customer_details.customer_phone' => 'required|string|regex:/^[6-9]\d{9}$/',
                'customer_details.customer_name' => 'nullable|string',
                'order_note' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'timestamp' => now()
                ], 400);
            }

            $input = $request->all();

            // Extract customer details
            $amount = floatval($input['order_amount']);
            $customerId = $input['customer_details']['customer_id'];
            $customerEmail = $input['customer_details']['customer_email'];
            $customerPhone = $input['customer_details']['customer_phone'];
            $customerName = $input['customer_details']['customer_name'] ?? 'Customer';

            // Generate unique order ID
            $orderId = 'order_' . time() . '_' . rand(10000, 99999);

            // Prepare order data for Cashfree
            $orderData = [
                'order_id' => $orderId,
                'order_amount' => number_format($amount, 2, '.', ''),
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                ],
                'order_meta' => [
                    'return_url' => $this->cashfreeConfig['return_url'] . '?order_id=' . $orderId,
                ],
                'order_note' => $input['order_note'] ?? 'Gold purchase',
            ];

            Log::info("Creating Cashfree order", [
                'order_id' => $orderId,
                'amount' => $amount,
                'customer_id' => $customerId
            ]);

            // Make API call to Cashfree
            $response = $this->createCashfreeOrder($orderData);

            if ($response && isset($response['order_id']) && isset($response['payment_session_id'])) {
                // Save order to database
                PaymentOrder::create([
                    'order_id' => $response['order_id'],
                    'cf_order_id' => $response['cf_order_id'] ?? null,
                    'order_amount' => $amount,
                    'order_currency' => 'INR',
                    'order_status' => $response['order_status'] ?? 'ACTIVE',
                    'payment_session_id' => $response['payment_session_id'],
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'order_note' => $input['order_note'] ?? 'Gold purchase',
                    'api_response' => [
                        'order_id' => $response['order_id'],
                        'cf_order_id' => $response['cf_order_id'] ?? null,
                        'order_status' => $response['order_status'] ?? 'ACTIVE',
                        'order_expiry_time' => $response['order_expiry_time'] ?? null,
                    ],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => [
                        'order_id' => $response['order_id'],
                        'payment_session_id' => $response['payment_session_id'],
                        'order_amount' => $amount,
                        'order_currency' => 'INR',
                        'order_status' => $response['order_status'] ?? 'ACTIVE',
                        'cf_order_id' => $response['cf_order_id'] ?? null,
                    ],
                    'timestamp' => now()
                ], 200);
            } else {
                $errorMsg = $response['message'] ?? 'Unknown error creating order';
                Log::error("Cashfree order creation failed", [
                    'order_id' => $orderId,
                    'response' => $response
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create order: ' . $errorMsg,
                    'timestamp' => now()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Payment order creation error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get Cashfree order status
     */
    public function getOrderStatus(Request $request)
    {
        try {
            $orderId = $request->query('order_id');

            if (!$orderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order ID is required',
                    'timestamp' => now()
                ], 400);
            }

            Log::info("Fetching order status", ['order_id' => $orderId]);

            // Fetch order status from Cashfree
            $response = $this->getCashfreeOrderStatus($orderId);

            if ($response && isset($response['order_id'])) {
                // Update order status in database if it exists
                $paymentOrder = PaymentOrder::where('order_id', $orderId)->first();
                if ($paymentOrder) {
                    $updateData = [
                        'order_status' => $response['order_status'] ?? 'UNKNOWN',
                        'api_response' => [
                            'order_id' => $response['order_id'],
                            'order_status' => $response['order_status'] ?? 'UNKNOWN',
                            'cf_order_id' => $response['cf_order_id'] ?? null,
                        ],
                    ];

                    // Mark as paid if status is PAID
                    if (($response['order_status'] ?? '') === 'PAID') {
                        $updateData['paid_at'] = now();
                        $updateData['payment_details'] = $response['payment_details'] ?? null;
                    }

                    DB::transaction(function () use ($paymentOrder, $updateData) {
                        $paymentOrder->update($updateData);
                    });
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Order status retrieved',
                    'data' => [
                        'order_id' => $response['order_id'],
                        'order_status' => $response['order_status'] ?? 'UNKNOWN',
                        'order_amount' => $response['order_amount'] ?? 0,
                        'payment_status' => $response['order_status'] ?? 'UNKNOWN',
                        'order_currency' => $response['order_currency'] ?? 'INR',
                        'payment_details' => $response['payment_details'] ?? null,
                    ],
                    'timestamp' => now()
                ], 200);
            } else {
                $errorMsg = $response['message'] ?? 'Failed to fetch order status';
                Log::error("Cashfree order status fetch failed", [
                    'order_id' => $orderId,
                    'response' => $response
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                    'timestamp' => now()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Order status fetch error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Create Cashfree order via API
     */
    private function createCashfreeOrder($orderData)
    {
        $url = $this->cashfreeConfig['api_url'] . '/orders';

        $headers = [
            'Content-Type: application/json',
            'x-api-version: ' . $this->cashfreeConfig['api_version'],
            'x-client-id: ' . $this->cashfreeConfig['app_id'],
            'x-client-secret: ' . $this->cashfreeConfig['secret_key'],
        ];

        Log::info("Cashfree API Request", [
            'url' => $url,
            'order_id' => $orderData['order_id']
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('services.cashfree.ssl_verify', true),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            Log::error("Cashfree API CURL Error", ['error' => $error]);
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }

        $result = json_decode($response, true);

        Log::info("Cashfree API Response", [
            'http_code' => $httpCode,
            'response' => $result
        ]);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'API error',
                'code' => $httpCode
            ];
        }

        return $result;
    }

    /**
     * Get Cashfree order status
     */
    private function getCashfreeOrderStatus($orderId)
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
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('services.cashfree.ssl_verify', true),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            Log::error("Cashfree Status API CURL Error", ['error' => $error]);
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }

        $result = json_decode($response, true);

        Log::info("Cashfree Status API Response", [
            'order_id' => $orderId,
            'http_code' => $httpCode,
            'response' => $result
        ]);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to fetch order status',
                'code' => $httpCode
            ];
        }

        return $result;
    }
}