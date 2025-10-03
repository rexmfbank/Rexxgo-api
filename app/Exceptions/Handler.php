<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{
    protected $levels = [];
    protected $dontReport = [];
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register()
    {
        //
    }

    public function render($request, Throwable $exception)
    {
        
        if ($request->wantsJson() || ($request->is('api/*'))) {

            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if ($exception instanceof \Illuminate\Database\QueryException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database error. Please try again later.',
                ], 500);
            }
            
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $exception->errors(),
                ], 422);
            }

            if ($exception instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            if ($exception instanceof HttpException) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage() ?: 'HTTP error',
                ], $exception->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'message' => 'Server Error',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
