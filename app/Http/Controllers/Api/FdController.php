<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use App\Http\Requests\CreateFdRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FdController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Create a Gold FD
     * POST /api/augmont/fd
     */
    public function store(CreateFdRequest $request)
    {
        $response = $this->augmont->createFd($request->validated());

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to create FD'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Gold FD created successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * List user FDs
     * GET /api/augmont/fd/{uniqueId}
     */
    public function index(string $uniqueId)
    {
        $response = $this->augmont->listFd($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch FDs'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD list retrieved',
            'data'    => ['fds' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get FD detail
     * GET /api/augmont/fd/{uniqueId}/{fdId}
     */
    public function show(string $uniqueId, string $fdId)
    {
        $response = $this->augmont->getFdDetail($fdId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch FD detail'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD detail retrieved',
            'data'    => ['fd' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get FD terms/tenure options
     * GET /api/augmont/fd/terms
     */
    public function terms(Request $request)
    {
        $schemeId = $request->query('schemeId', '');
        $uniqueId = $request->query('uniqueId', '');

        if (!$schemeId || !$uniqueId) {
            return response()->json(['success' => false, 'message' => 'schemeId and uniqueId are required'], 400);
        }

        $response = $this->augmont->getFdTerms($schemeId, $uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch FD terms'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD terms retrieved',
            'data'    => ['terms' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get FD schemes
     * GET /api/augmont/fd/schemes
     */
    public function schemes()
    {
        $response = $this->augmont->getFdSchemes();

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch FD schemes'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD schemes retrieved',
            'data'    => ['schemes' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Close an FD
     * POST /api/augmont/fd/{uniqueId}/{fdId}/close
     */
    public function close(string $uniqueId, string $fdId)
    {
        $response = $this->augmont->closeFd($fdId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to close FD'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD closed successfully',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * Get FD passbook
     * GET /api/augmont/fd/{uniqueId}/{fdId}/passbook
     */
    public function passbook(string $uniqueId, string $fdId)
    {
        $response = $this->augmont->getFdPassbook($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch FD passbook'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD passbook retrieved',
            'data'    => ['passbook' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * FD pre-order (lock price)
     * POST /api/augmont/fd/pre-order
     */
    public function preOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uniqueId'   => 'required|string',
            'goldWeight'  => 'required|numeric|min:0.5',
            'metalType'   => 'required|in:gold,silver',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $response = $this->augmont->fdPreOrder($request->all());

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to create pre-order'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD pre-order created (price locked)',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * FD pre-close calculation
     * POST /api/augmont/fd/{uniqueId}/{fdId}/pre-close-calc
     */
    public function preCloseCalc(string $uniqueId, string $fdId)
    {
        $response = $this->augmont->fdPreCloseCalc($fdId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to calculate pre-close'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pre-close calculation retrieved',
            'data'    => $response['data'] ?? null,
        ]);
    }

    /**
     * FD transactions list
     * GET /api/augmont/fd/{uniqueId}/transactions
     */
    public function transactions(string $uniqueId)
    {
        $response = $this->augmont->getFdTransactions($uniqueId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch FD transactions'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'FD transactions retrieved',
            'data'    => ['transactions' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
