<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\GoldRate;
use App\Models\GoldTransaction;

class UserController extends Controller
{
    /**
     * Get User Profile
     */
    public function profile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            // Mask sensitive data (Aadhaar)
            $maskedAadhaar = $user->aadhaar;
            if ($user->aadhaar && strlen($user->aadhaar) >= 8) {
                $maskedAadhaar = substr($user->aadhaar, 0, 4) . '****' . substr($user->aadhaar, -4);
            }

            // Check dynamic KYC status from verification tables
            $panVerified = DB::table('pan_verifications')
                ->where('mobile', $user->mobile)
                ->where('is_verified', true)
                ->exists();

            $aadhaarVerified = DB::table('aadhaar_verifications')
                ->where('mobile', $user->mobile)
                ->where('is_verified', true)
                ->exists();

            $kycStatus = ($panVerified && $aadhaarVerified) ? 'verified' : (($panVerified || $aadhaarVerified) ? 'partial' : 'pending');

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'pan' => $user->pan,
                'aadhaar' => $maskedAadhaar,
                'kyc_status' => $kycStatus,
                'kyc_verified_at' => $user->kyc_verified_at,
                'created_at' => $user->created_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => $userData
            ], 200);

        } catch (\Exception $e) {
            Log::error("Get Profile Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update User Profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'name'    => 'sometimes|string|max:255',
                'email'   => 'sometimes|nullable|email|unique:users,email,' . $user->id,
                'pan'     => 'sometimes|nullable|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/|unique:users,pan,' . $user->id,
                'aadhaar' => 'sometimes|nullable|digits:12|unique:users,aadhaar,' . $user->id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Only update fields that are actually provided
            $fillable = ['name', 'email', 'pan', 'aadhaar'];
            foreach ($fillable as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    $user->$field = ($field === 'pan') ? strtoupper($value) : $value;
                }
            }
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error("Update Profile Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * User Dashboard
     */
    public function dashboard(Request $request)
    {
        try {
            $user = Auth::user();

            // 1. Get Gold Rates (reuse GoldController logic or Model)
            // Using GoldRate model to get latest rate
            $latestRate = GoldRate::orderBy('created_at', 'desc')->first();
            
            // If no rate in DB, fetch from config or default (ideally call Augmont service)
            // For now, use DB values or placeholders
            $buyPrice = $latestRate ? $latestRate->buy_rate : 0;
            $sellPrice = $latestRate ? $latestRate->sell_rate : 0;

            // 2. Calculate Gold Balance
            // Sum of buy grams - Sum of sell grams
            // Note: need to handle logic if 'status' column exists and is 'completed'
            $buyGrams = GoldTransaction::where('user_id', $user->id)
                ->where('transaction_type', 'buy')
                ->sum('quantity');

            $sellGrams = GoldTransaction::where('user_id', $user->id)
                ->where('transaction_type', 'sell')
                ->sum('quantity');

            $totalGrams = $buyGrams - $sellGrams;
            $totalValue = $totalGrams * $sellPrice;

            // 3. Recent Transactions (limit 5)
            $goldTransactions = GoldTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($txn) {
                    return [
                        'id' => $txn->id,
                        'type' => $txn->transaction_type,
                        'amount' => (float)$txn->amount,
                        'grams' => (float)$txn->quantity,
                        'status' => $txn->status,
                        'date' => $txn->created_at,
                    ];
                });
            
            // Merge SIP transactions when SIP feature is implemented
            $recentTransactions = $goldTransactions; 

            // 4. Stats
            $totalTransactionsCount = GoldTransaction::where('user_id', $user->id)->count();

            $dashboardData = [
                'user' => [
                    'name' => $user->name,
                    'kyc_status' => $user->kyc_status ?? 'pending'
                ],
                'balance_grams' => number_format($totalGrams, 4),
                'balance_inr' => number_format($totalValue, 2),
                'buy_price' => number_format($buyPrice, 2),
                'sell_price' => number_format($sellPrice, 2),
                'transactions' => $recentTransactions,
                'stats' => [
                    'total_transactions' => $totalTransactionsCount
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => $dashboardData
            ], 200);

        } catch (\Exception $e) {
            Log::error("Dashboard Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * User Transactions List
     */
    public function transactions(Request $request)
    {
        try {
            $user = Auth::user();
            
            $type = $request->query('type', 'all');
            $limit = (int) $request->query('limit', 50);
            $limit = min($limit, 100); // Max limit 100

            // Query Builder
            $query = GoldTransaction::where('user_id', $user->id);

            if ($type !== 'all') {
                $query->where('transaction_type', $type);
            }

            // Pagination
            $transactions = $query->orderBy('created_at', 'desc')->paginate($limit);

            // Format data
            $formattedTransactions = $transactions->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'type' => $txn->transaction_type,
                    'amount' => (float)$txn->amount,
                    'grams' => (float)$txn->quantity,
                    'rate' => (float)$txn->rate,
                    'status' => $txn->status,
                    'txn_id' => $txn->transaction_id,
                    'payment_mode' => $txn->payment_method ?? 'Unknown', // column name check needed
                    'date' => $txn->created_at->toDateTimeString()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => [
                    'transactions' => $formattedTransactions,
                    'pagination' => [
                        'total' => $transactions->total(),
                        'per_page' => $transactions->perPage(),
                        'current_page' => $transactions->currentPage(),
                        'last_page' => $transactions->lastPage(),
                        'from' => $transactions->firstItem(),
                        'to' => $transactions->lastItem(),
                        'has_more' => $transactions->hasMorePages()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
             Log::error("Get Transactions Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
