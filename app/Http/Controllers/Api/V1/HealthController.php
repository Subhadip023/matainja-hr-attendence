<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponder::success([
            'status' => 'ok',
            'time' => now()->toIso8601String(),
        ], 'Service healthy');
    }
}
