<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Render all API exceptions as standardised JSON
        $this->renderable(function (Throwable $e, $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['success' => false, 'message' => 'Resource not found'], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json(['success' => false, 'message' => 'Endpoint not found'], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if ($e instanceof TooManyRequestsHttpException) {
                return response()->json(['success' => false, 'message' => 'Too many requests. Please try again later.'], 429);
            }

            if ($e instanceof AccessDeniedHttpException) {
                return response()->json(['success' => false, 'message' => 'Access denied'], 403);
            }

            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], $statusCode);
        });
    }
}
