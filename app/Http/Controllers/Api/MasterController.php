<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;
use Illuminate\Support\Facades\Cache;

class MasterController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * Get list of states
     * GET /api/master/states
     */
    public function states()
    {
        $data = Cache::remember('augmont_states', 86400, function () {
            $response = $this->augmont->getStates();
            return $response['success'] ? $response['data'] : null;
        });

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch states'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'States retrieved',
            'data'    => ['states' => $data, 'source' => 'augmont'],
        ]);
    }

    /**
     * Get list of cities for a state
     * GET /api/master/cities/{stateId}
     */
    public function cities(string $stateId)
    {
        $data = Cache::remember("augmont_cities_{$stateId}", 86400, function () use ($stateId) {
            $response = $this->augmont->getCities(['stateId' => $stateId]);
            return $response['success'] ? $response['data'] : null;
        });

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch cities'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cities retrieved',
            'data'    => ['cities' => $data, 'source' => 'augmont'],
        ]);
    }

    /**
     * Get list of supported banks
     * GET /api/master/banks
     */
    public function banks()
    {
        $data = Cache::remember('augmont_banks', 86400, function () {
            $response = $this->augmont->getBanks();
            return $response['success'] ? $response['data'] : null;
        });

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch banks'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Banks retrieved',
            'data'    => ['banks' => $data, 'source' => 'augmont'],
        ]);
    }
}
