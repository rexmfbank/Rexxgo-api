<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Auth\AuthenticationException;

class JsonExceptionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        } catch (MethodNotAllowedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'HTTP method not allowed',
            ], 405);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'HTTP error',
            ], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error',
                'error' => $e->getMessage(), // remove in production
            ], 500);
        }
    }
}
