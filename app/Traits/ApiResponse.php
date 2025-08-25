<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a success JSON response
     *
     * @param mixed  $data
     * @param string $message
     * @param int    $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data
        ], $code);
    }

    /**
     * Return an error JSON response
     *
     * @param string $message
     * @param int    $code
     * @param mixed  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors
        ], $code);
    }

    /**
     * Return a generic JSON response
     *
     * @param bool   $success
     * @param string $message
     * @param mixed  $data
     * @param int    $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respond(bool $success, string $message, $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => $success ? 'success' : 'error',
            'message' => $message,
            'data'    => $data
        ], $code);
    }
}
