<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AugmontService;

class ProductController extends Controller
{
    public function __construct(private AugmontService $augmont) {}

    /**
     * List available gold/silver products
     * GET /api/augmont/products
     */
    public function index()
    {
        $response = $this->augmont->listProducts();

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch products'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved',
            'data'    => ['products' => $response['data'], 'source' => 'augmont'],
        ]);
    }

    /**
     * Get single product detail
     * GET /api/augmont/products/{productId}
     */
    public function show(string $productId)
    {
        $response = $this->augmont->getProduct($productId);

        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch product'], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product detail retrieved',
            'data'    => ['product' => $response['data'], 'source' => 'augmont'],
        ]);
    }
}
