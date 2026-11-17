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
            $value = $setting['value'] ?? null;

            CompanySetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $this->storedValue($value),
                    'data_type' => match (true) {
                        is_bool($value) => 'boolean',
                        is_scalar($value) || $value === null => 'string',
                        default => 'json',
                    },
                ]
            );
        }

        AuditLog::record('settings.updated', null, null, $validated['settings']);

        return ApiResponder::success(
            CompanySetting::pluck('value', 'key'),
            'Settings updated'
        );
    }

    /**
     * Booleans are stored as "true"/"false" (a plain string cast would turn
     * false into "" and make it unreadable); arrays are JSON-encoded.
     */
    private function storedValue(mixed $value): ?string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => null,
            is_scalar($value) => (string) $value,
            default => json_encode($value),
        };
    }
}
