<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserBank;
use App\Services\AugmontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Create user bank account (Augmont)
     * POST /api/augmont/banks
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uniqueId'      => 'required|string',
            'accountNumber' => 'required|string|min:8|max:18|regex:/^[0-9]+$/',
            'accountName'   => 'required|string|min:2|max:100',
            'ifscCode'      => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'bankId'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->createUserBank($request->uniqueId, $request->only([
            'accountNumber', 'accountName', 'ifscCode', 'bankId',
        ]));

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to add bank'], 503);
        }

        // Also save locally
        $user = $request->user();
        if ($user) {
            UserBank::create([
                'user_id'        => $user->id,
                'account_number' => $request->accountNumber,
                'account_name'   => $request->accountName,
                'ifsc_code'      => $request->ifscCode,
                'bank_id'        => $request->bankId,
                'status'         => 'active',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank account added successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * List user bank accounts (Augmont)
     * GET /api/augmont/banks/{uniqueId}
     */
    public function index(string $uniqueId)
    {
        $response = $this->augmont->getUserBanks($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch banks'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank accounts retrieved',
            'data'    => ['banks' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Delete user bank account (Augmont)
     * DELETE /api/augmont/banks/{uniqueId}/{bankId}
     */
    public function destroy(string $uniqueId, string $bankId)
    {
        $response = $this->augmont->call('DELETE', "/merchant/v1/users/{$uniqueId}/banks/{$bankId}");

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to delete bank'], 503);
        }

        // Also deactivate locally
        UserBank::where('bank_id', $bankId)->update(['status' => 'inactive']);

        return response()->json([
            'success' => true,
            'message' => 'Bank account deleted successfully',
        ]);
    }

    /**
     * Validate IFSC code against master list
     * GET /api/banks/validate-ifsc/{ifsc}
     */
    public function validateIfsc(string $ifsc)
    {
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            return response()->json(['success' => false, 'message' => 'Invalid IFSC format'], 400);
        }

        $banks = $this->augmont->getBanks();

        if (!$banks['success']) {
            return response()->json(['success' => false, 'message' => 'Unable to validate IFSC at this time'], 503);
        }

        $bankList = $banks['data'] ?? [];
        $matched  = collect($bankList)->first(fn ($b) => str_starts_with($ifsc, $b['ifscPrefix'] ?? ''));

        return response()->json([
            'success' => (bool) $matched,
            'message' => $matched ? 'Valid IFSC code' : 'IFSC code not found in supported banks',
            'data'    => $matched ? ['bank' => $matched] : null,
        ]);
    }

    // =============================================
    // LOCAL BANK CRUD (without Augmont)
    // =============================================

    /**
     * Add bank account (local DB only, no Augmont)
     * POST /api/banks/add
     */
    public function addLocal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|min:8|max:18|regex:/^[0-9]+$/',
            'account_name'   => 'required|string|min:2|max:100',
            'ifsc_code'      => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'bank_name'      => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $user = $request->user();

        // Check duplicate
        $exists = UserBank::where('user_id', $user->id)
            ->where('account_number', $request->account_number)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Bank account with this account number already exists'], 400);
        }

        $bank = UserBank::create([
            'user_id'        => $user->id,
            'account_number' => $request->account_number,
            'account_name'   => $request->account_name,
            'ifsc_code'      => $request->ifsc_code,
            'bank_name'      => $request->bank_name,
            'status'         => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank account added successfully',
            'data'    => ['bank_account' => $bank],
        ]);
    }

    /**
     * Update bank account (local DB only)
     * PUT /api/banks/{id}
     */
    public function updateLocal(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|min:8|max:18|regex:/^[0-9]+$/',
            'account_name'   => 'required|string|min:2|max:100',
            'ifsc_code'      => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'bank_name'      => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $user = $request->user();
        $bank = UserBank::where('id', $id)->where('user_id', $user->id)->first();

        if (!$bank) {
            return response()->json(['success' => false, 'message' => 'Bank account not found or access denied'], 404);
        }

        // Check duplicate account number (exclude current)
        $duplicate = UserBank::where('user_id', $user->id)
            ->where('account_number', $request->account_number)
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            return response()->json(['success' => false, 'message' => 'Bank account with this account number already exists'], 400);
        }

        $bank->update([
            'account_number' => $request->account_number,
            'account_name'   => $request->account_name,
            'ifsc_code'      => $request->ifsc_code,
            'bank_name'      => $request->bank_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank account updated successfully',
            'data'    => ['bank_account' => $bank->fresh()],
        ]);
    }

    /**
     * Delete bank account (local DB only)
     * DELETE /api/banks/{id}
     */
    public function deleteLocal(Request $request, int $id)
    {
        $user = $request->user();
        $bank = UserBank::where('id', $id)->where('user_id', $user->id)->first();

        if (!$bank) {
            return response()->json(['success' => false, 'message' => 'Bank account not found or access denied'], 404);
        }

        $bank->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank account deleted successfully',
        ]);
    }

    /**
     * List user's local bank accounts
     * GET /api/banks
     */
    public function listLocal(Request $request)
    {
        $banks = UserBank::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Bank accounts retrieved',
            'data'    => ['banks' => $banks],
        ]);
    }

    // =============================================
    // BANK ACCOUNT VERIFICATION (Cashfree Penny Drop)
    // =============================================

    /**
     * Verify bank account using Cashfree Verification API (Penny Drop)
     * POST /api/banks/verify
     *
     * Sends ₹1 to the account and verifies:
     * - Account exists
     * - Account holder name matches
     * - Account is active
     */
    public function verifyBankAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_id'        => 'nullable|integer|exists:user_banks,id',
            'account_number' => 'required_without:bank_id|string|min:8|max:18|regex:/^[0-9]+$/',
            'ifsc_code'      => 'required_without:bank_id|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'account_name'   => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $user = $request->user();

        // If bank_id provided, fetch from DB
        if ($request->bank_id) {
            $bank = UserBank::where('id', $request->bank_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bank) {
                return response()->json(['success' => false, 'message' => 'Bank account not found'], 404);
            }

            // Already verified? Return cached result
            if ($bank->is_verified && $bank->verified_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank account already verified',
                    'data'    => [
                        'account_status'  => 'VALID',
                        'name_at_bank'    => $bank->name_at_bank,
                        'verified_at'     => $bank->verified_at,
                        'already_verified' => true,
                    ],
                ]);
            }

            $accountNumber = $bank->account_number;
            $ifscCode      = $bank->ifsc_code;
            $accountName   = $bank->account_name;
        } else {
            $bank          = null;
            $accountNumber = $request->account_number;
            $ifscCode      = $request->ifsc_code;
            $accountName   = $request->account_name ?? '';
        }

        try {
            $config = config('services.cashfree_verification');

            if (empty($config['client_id']) || empty($config['client_secret'])) {
                Log::warning('Cashfree Verification API credentials not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Bank verification service not configured. Please contact support.',
                ], 503);
            }

            // Call Cashfree Bank Account Verification API
            $result = $this->callCashfreeVerification($config, [
                'bank_account' => $accountNumber,
                'ifsc'         => $ifscCode,
                'name'         => $accountName,
                'phone'        => $user->mobiles ?? $user->mobile ?? '',
            ]);

            Log::info('Cashfree Bank Verification Response', [
                'user_id'        => $user->id,
                'account_number' => substr($accountNumber, 0, 4) . '****',
                'result'         => $result,
            ]);

            if (!$result['success']) {
                // Update bank record with failed status
                if ($bank) {
                    $bank->update([
                        'is_verified'         => false,
                        'verification_status' => $result['account_status'] ?? 'FAILED',
                        'verification_ref'    => $result['ref_id'] ?? null,
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Bank account verification failed',
                    'data'    => [
                        'account_status' => $result['account_status'] ?? 'INVALID',
                        'reason'         => $result['message'] ?? 'Verification failed',
                    ],
                ], 422);
            }

            // Verification successful — update bank record
            if ($bank) {
                $bank->update([
                    'is_verified'         => true,
                    'verified_at'         => now(),
                    'name_at_bank'        => $result['name_at_bank'] ?? null,
                    'verification_status' => $result['account_status'] ?? 'VALID',
                    'verification_ref'    => $result['ref_id'] ?? null,
                    'verification_utr'    => $result['utr'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank account verified successfully',
                'data'    => [
                    'account_status'  => $result['account_status'] ?? 'VALID',
                    'name_at_bank'    => $result['name_at_bank'] ?? null,
                    'ref_id'          => $result['ref_id'] ?? null,
                    'utr'             => $result['utr'] ?? null,
                    'verified_at'     => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Bank verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bank verification service temporarily unavailable',
            ], 500);
        }
    }

    /**
     * Call Cashfree Bank Account Verification (Penny Drop) API
     *
     * API: POST {base_url}/bank-account/sync
     * Docs: https://docs.cashfree.com/reference/bank-account-verification
     */
    private function callCashfreeVerification(array $config, array $data): array
    {
        $url = rtrim($config['api_url'], '/') . '/bank-account/sync';

        $headers = [
            'Content-Type: application/json',
            'x-client-id: ' . $config['client_id'],
            'x-client-secret: ' . $config['client_secret'],
        ];

        $payload = [
            'bank_account' => $data['bank_account'],
            'ifsc'         => $data['ifsc'],
        ];
        if (!empty($data['name']))  $payload['name']  = $data['name'];
        if (!empty($data['phone'])) $payload['phone'] = $data['phone'];

        Log::info('Cashfree Verification API Request', [
            'url'     => $url,
            'account' => substr($data['bank_account'], 0, 4) . '****',
            'ifsc'    => $data['ifsc'],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('services.cashfree.ssl_verify', true),
            CURLOPT_TIMEOUT        => 60, // Penny drop can take time
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('Cashfree Verification CURL Error', ['error' => $error]);
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }

        $result = json_decode($response, true);

        Log::info('Cashfree Verification API Response', [
            'http_code' => $httpCode,
            'response'  => $result,
        ]);

        if ($httpCode !== 200 || !$result) {
            return [
                'success'        => false,
                'message'        => $result['message'] ?? 'Verification API error (HTTP ' . $httpCode . ')',
                'account_status' => $result['account_status'] ?? 'ERROR',
                'ref_id'         => $result['ref_id'] ?? null,
            ];
        }

        // Cashfree returns account_status: "VALID" or "INVALID"
        $isValid = ($result['account_status'] ?? '') === 'VALID';

        return [
            'success'        => $isValid,
            'account_status' => $result['account_status'] ?? 'UNKNOWN',
            'name_at_bank'   => $result['name_at_bank'] ?? null,
            'ref_id'         => $result['ref_id'] ?? null,
            'utr'            => $result['utr'] ?? null,
            'message'        => $isValid
                ? 'Account verified successfully'
                : ('Account verification failed: ' . ($result['account_status_code'] ?? 'unknown reason')),
        ];
    }
}
