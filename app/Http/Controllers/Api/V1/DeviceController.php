<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:android,ios,web'],
            'device_identifier' => ['required', 'string', 'max:255'],
            'push_token' => ['nullable', 'string', 'max:512'],
        ]);

        $device = $request->user()->devices()->updateOrCreate(
            ['device_identifier' => $validated['device_identifier']],
            [
                'platform' => $validated['platform'],
                'push_token' => $validated['push_token'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return ApiResponder::success($device, 'Device registered', 201);
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            return ApiResponder::error('Not found.', 404);
        }

        $device->delete();

        return ApiResponder::success(null, 'Device unregistered');
    }
}
