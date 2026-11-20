<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'invoicing' => ['nullable', 'array'],
            'invoicing.*' => ['nullable', 'string', 'max:2000'],
            // Form fields use "_" for "." (company.name -> invoicing[company_name]).
            'invoicing.invoice_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'invoicing.invoice_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/_-]+$/'],
            // PNG/JPG only: the PDF engine can't draw WebP or SVG reliably.
            'company_logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=3000,max_height=3000'],
            'remove_logo' => ['nullable', 'boolean'],
            'custom' => ['nullable', 'array'],
            'custom.*' => ['nullable', 'string', 'max:2000'],
            'new_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            'new_value' => ['nullable', 'string', 'max:2000', 'required_with:new_key'],
        ], [
            'new_key.regex' => 'Keys may use lowercase letters, numbers, dots and underscores.',
            'company_logo.mimes' => 'The logo must be a PNG or JPG image.',
            'company_logo.max' => 'The logo must be 1 MB or smaller.',
        ]);

        $before = CompanySetting::pluck('value', 'key')->all();

        $days = collect($data['wfh_weekdays'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->implode(',');
        $this->put(AttendanceService::SETTING_WFH_WEEKDAYS, $days, 'string');
        $this->put(GeofenceService::SETTING_ENFORCED, $request->boolean('geofence_enforced') ? 'true' : 'false', 'boolean');
        if (! empty($data['geofence_radius_meters'])) {
            $this->put(GeofenceService::SETTING_DEFAULT_RADIUS, (string) $data['geofence_radius_meters'], 'string');
        }

        if ($request->has('invoicing')) {
            $invoicing = (array) $request->input('invoicing');
            foreach (array_keys(self::invoicingKeys()) as $key) {
                $value = trim((string) ($invoicing[str_replace('.', '_', $key)] ?? ''));
                $this->put($key, $value === '' ? null : $value, 'string');
            }
        }

        $this->saveLogo($request);

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

    /** Upload a new logo (replacing the old file) or remove it. */
    private function saveLogo(Request $request): void
    {
        $old = CompanySetting::where('key', InvoiceService::SETTING_LOGO)->value('value');

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('company', 'local');
            $this->put(InvoiceService::SETTING_LOGO, $path, 'string');
        } elseif ($request->boolean('remove_logo')) {
            $this->put(InvoiceService::SETTING_LOGO, null, 'string');
        } else {
            return;
        }

        if ($old) {
            Storage::disk('local')->delete($old);
        }
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
            InvoiceService::SETTING_LOGO => 'Organisation logo',
        ] + self::invoicingKeys();
    }

    /** Settings printed on / used for invoices. @return array<string, string> */
    public static function invoicingKeys(): array
    {
        return InvoiceService::COMPANY_SETTINGS + [
            InvoiceService::SETTING_PREFIX => 'Invoice number prefix',
            InvoiceService::SETTING_DUE_DAYS => 'Days until due',
            InvoiceService::SETTING_TERMS => 'Default payment terms',
        ];
    }
}
