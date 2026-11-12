<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponder
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200, mixed $meta = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
