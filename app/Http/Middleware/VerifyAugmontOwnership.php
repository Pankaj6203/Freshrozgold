<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ensures the authenticated user actually owns the Augmont account
 * referenced by {uniqueId} in the route or request body.
 */
class VerifyAugmontOwnership
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->augmont_user_id) {
            // User has no linked Augmont account yet — let the controller decide.
            return $next($request);
        }

        // Resolve uniqueId from route param, then body, then transfer-specific key
        $uniqueId = $request->route('uniqueId')
            ?? $request->input('uniqueId')
            ?? $request->input('fromUniqueId');

        if ($uniqueId && $uniqueId !== $user->augmont_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied: Augmont account mismatch',
            ], 403);
        }

        return $next($request);
    }
}
