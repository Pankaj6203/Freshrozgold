<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Fix Authorization header for CGI/FastCGI servers.
 * 
 * Apache CGI-FCGI mode strips the Authorization header before passing to PHP.
 * This middleware recovers the token from alternative sources:
 * 1. X-Authorization header (custom, never stripped)
 * 2. REDIRECT_HTTP_AUTHORIZATION server var (set by .htaccess RewriteRule)
 * 3. HTTP_AUTHORIZATION server var (set by SetEnvIf)
 * 4. ?token= query parameter (last resort)
 */
class SetAuthorizationHeader
{
    public function handle(Request $request, Closure $next)
    {
        // If Authorization header already exists, do nothing
        if ($request->headers->has('Authorization') && $request->headers->get('Authorization') !== '') {
            return $next($request);
        }

        $token = null;

        // Try X-Authorization custom header (most reliable for CGI)
        if ($request->headers->has('X-Authorization')) {
            $token = $request->headers->get('X-Authorization');
        }
        // Try REDIRECT_HTTP_AUTHORIZATION (set by .htaccess RewriteRule)
        elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        // Try HTTP_AUTHORIZATION from $_SERVER
        elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $token = $_SERVER['HTTP_AUTHORIZATION'];
        }
        // Try getallheaders() directly
        elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $token = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
        // Last resort: query parameter
        elseif ($request->has('token')) {
            $token = 'Bearer ' . $request->query('token');
        }

        if ($token) {
            $request->headers->set('Authorization', $token);
            // Also set in $_SERVER so Sanctum's guard can find it
            $_SERVER['HTTP_AUTHORIZATION'] = $token;
        }

        return $next($request);
    }
}
