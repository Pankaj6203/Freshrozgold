<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SipPlan;
use App\Models\SipTransaction;
use App\Services\AugmontService;
use App\Http\Requests\CreateSipRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SipController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Get SIP rates from Augmont
     * GET /api/augmont/sip/rates
     */
    public function rates()
    {
        $response = $this->augmont->getSipRates();

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch SIP rates'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'SIP rates retrieved',
            'data'    => ['rates' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Create a new SIP plan
     * POST /api/augmont/sip
     */
    public function store(CreateSipRequest $request)
    {
        $user = $request->user();

        // KYC check (matching core PHP sip/create.php)
        if (!$user->kyc_status || $user->kyc_status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification required before creating SIP plan',
            ], 403);
        }

        $plan = SipPlan::create([
            'user_id'              => $user->id,
            'amount'               => $request->amount,
            'frequency'            => $request->frequency,
            'duration_months'      => $request->duration_months,
            'start_date'           => $request->start_date,
            'next_investment_date' => $request->start_date,
            'status'               => 'active',
            'total_investments'    => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SIP plan created successfully',
            'data'    => ['plan' => $plan],
        ]);
    }

    /**
     * List user SIP plans
     * GET /api/augmont/sip
     */
    public function index(Request $request)
    {
        $plans = SipPlan::where('user_id', $request->user()->id)
            ->with('transactions')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'SIP plans retrieved',
            'data'    => ['plans' => $plans],
        ]);
    }

    /**
     * Cancel / pause a SIP plan
     * PUT /api/augmont/sip/{id}
     */
    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,paused,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $plan = SipPlan::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $plan->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'SIP plan updated to ' . $request->status,
            'data'    => ['plan' => $plan->fresh()],
        ]);
    }
}
