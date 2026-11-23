<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Role;
use App\Models\Shift;
use App\Services\StaffImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk-create employees from a CSV: upload -> preview (nothing saved) -> import.
 * The cleaned rows wait in the session between the preview and the import.
 */
class StaffImportController extends AdminController
{
    private const MAX_ROWS = 500;

    public function __construct(private readonly StaffImportService $importer) {}

    public function create(): View
    {
        return view('admin.staff.import', [
            'roles' => Role::orderBy('id')->pluck('name'),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'statuses' => StaffController::STATUSES,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $options = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'create_logins' => ['boolean'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'employment_status' => ['required', Rule::in(StaffController::STATUSES)],
        ]);

        $parsed = $this->importer->parse($request->file('file')->getRealPath());
        if ($parsed['error']) {
            return back()->withInput()->with('error', $parsed['error']);
        }
        if (count($parsed['rows']) > self::MAX_ROWS) {
            return back()->withInput()->with('error', 'Import up to '.self::MAX_ROWS.' employees at a time; split the file.');
        }

        $request->session()->put('staff_import', [
            'file_name' => $request->file('file')->getClientOriginalName(),
            'rows' => $parsed['rows'],
            'options' => [
                'create_logins' => $request->boolean('create_logins'),
                'role' => $options['role'],
                'branch_id' => isset($options['branch_id']) ? (int) $options['branch_id'] : null,
                'department_id' => isset($options['department_id']) ? (int) $options['department_id'] : null,
                'shift_id' => isset($options['shift_id']) ? (int) $options['shift_id'] : null,
                'employment_status' => $options['employment_status'],
            ],
        ]);

        return redirect()->route('admin.staff.import.preview');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $import = $request->session()->get('staff_import');
        if (! $import) {
            return redirect()->route('admin.staff.import');
        }

        return view('admin.staff.import-preview', [
            'import' => $import,
            'branch' => $import['options']['branch_id'] ? Branch::find($import['options']['branch_id'])?->name : null,
            'department' => $import['options']['department_id'] ? Department::find($import['options']['department_id'])?->name : null,
            'shift' => $import['options']['shift_id'] ? Shift::find($import['options']['shift_id'])?->name : null,
            'readyCount' => collect($import['rows'])->where('errors', [])->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $import = $request->session()->pull('staff_import');
        if (! $import) {
            return redirect()->route('admin.staff.import')->with('error', 'Nothing to import. Upload the file again.');
        }

        $result = $this->importer->import($import['rows'], $import['options']);

        AuditLog::record('staff.imported', null, null, [
            'file' => $import['file_name'],
            'created' => array_column($result['created'], 'id'),
            'skipped' => count($result['skipped']),
        ]);

        $request->session()->put('staff_import_result', $result);

        return redirect()->route('admin.staff.import.result');
    }

    public function result(Request $request): View|RedirectResponse
    {
        $result = $request->session()->get('staff_import_result');
        if (! $result) {
            return redirect()->route('admin.staff.index');
        }

        return view('admin.staff.import-result', ['result' => $result]);
    }

    /** The new app logins with their first passwords, for handing out. */
    public function logins(Request $request): StreamedResponse|RedirectResponse
    {
        $created = collect($request->session()->get('staff_import_result.created', []))->whereNotNull('password');
        if ($created->isEmpty()) {
            return redirect()->route('admin.staff.index');
        }

        return response()->streamDownload(function () use ($created) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Employee code', 'Login email', 'Password']);
            foreach ($created as $row) {
                fputcsv($out, [$row['name'], $row['code'], $row['email'], $row['password']]);
            }
            fclose($out);
        }, 'employee-logins-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, StaffImportService::TEMPLATE_HEADERS);
            fputcsv($out, ['Asha', 'Roy', 'asha.roy@example.com', '9876543210', 'M-140', '01/12/2026', 'Web Developer', 'Frontend Developer', '']);
            fclose($out);
        }, 'employee-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
