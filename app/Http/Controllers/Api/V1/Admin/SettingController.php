<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(
            CompanySetting::pluck('value', 'key'),
            'Settings retrieved'
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($validated['settings'] as $setting) {
            CompanySetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => is_scalar($setting['value'] ?? null) ? $setting['value'] : json_encode($setting['value']),
                    'data_type' => is_scalar($setting['value'] ?? null) ? 'string' : 'json',
                ]
            );
        }

        AuditLog::record('settings.updated', null, null, $validated['settings']);

        return ApiResponder::success(
            CompanySetting::pluck('value', 'key'),
            'Settings updated'
        );
    }
}
