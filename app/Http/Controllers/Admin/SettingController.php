<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends AdminController
{
    public function index(): View
    {
        $stored = CompanySetting::orderBy('key')->get()->keyBy('key');
        $known = array_keys($this->known());

        return view('admin.settings.index', [
            'known' => $this->known(),
            'stored' => $stored,
            'wfhDays' => array_map('intval', array_filter(explode(',', (string) ($stored[AttendanceService::SETTING_WFH_WEEKDAYS]->value ?? AttendanceService::DEFAULT_WFH_WEEKDAYS)), 'strlen')),
            'others' => $stored->except($known),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wfh_weekdays' => ['nullable', 'array'],
            'wfh_weekdays.*' => ['integer', 'between:1,7'],
            'geofence_enforced' => ['nullable', 'boolean'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:10', 'max:100000'],
            'custom' => ['nullable', 'array'],
            'custom.*' => ['nullable', 'string', 'max:2000'],
            'new_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            'new_value' => ['nullable', 'string', 'max:2000', 'required_with:new_key'],
        ], ['new_key.regex' => 'Keys may use lowercase letters, numbers, dots and underscores.']);

        $before = CompanySetting::pluck('value', 'key')->all();

        $days = collect($data['wfh_weekdays'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->implode(',');
        $this->put(AttendanceService::SETTING_WFH_WEEKDAYS, $days, 'string');
        $this->put(GeofenceService::SETTING_ENFORCED, $request->boolean('geofence_enforced') ? 'true' : 'false', 'boolean');
        if (! empty($data['geofence_radius_meters'])) {
            $this->put(GeofenceService::SETTING_DEFAULT_RADIUS, (string) $data['geofence_radius_meters'], 'string');
        }

        foreach ($data['custom'] ?? [] as $key => $value) {
            if (! array_key_exists($key, $this->known())) {
                $this->put($key, $value, 'string');
            }
        }
        if (! empty($data['new_key'])) {
            $this->put($data['new_key'], $data['new_value'], 'string');
        }

        AuditLog::record('settings.updated', null, $before, CompanySetting::pluck('value', 'key')->all());

        return back()->with('success', 'Settings saved. The mobile app uses them from the next check-in.');
    }

    private function put(string $key, ?string $value, string $type): void
    {
        CompanySetting::updateOrCreate(['key' => $key], ['value' => $value, 'data_type' => $type]);
    }

    /** @return array<string, string> */
    private function known(): array
    {
        return [
            AttendanceService::SETTING_WFH_WEEKDAYS => 'Work-from-home weekdays',
            GeofenceService::SETTING_ENFORCED => 'Enforce office geofence',
            GeofenceService::SETTING_DEFAULT_RADIUS => 'Default geofence radius',
        ];
    }
}
