<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appends a `timestamp` key to every API JSON response for consistency.
 */
class AppendApiTimestamp
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (is_array($data) && !isset($data['timestamp'])) {
                $data['timestamp'] = now()->toIso8601String();
                $response->setData($data);
            }
        }

        return $response;
    }
}
