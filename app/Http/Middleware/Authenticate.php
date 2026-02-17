<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API requests should never redirect — always return null so
        // Laravel sends a 401 JSON response instead of a redirect to /login
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }
        return route('login');
    }
}
