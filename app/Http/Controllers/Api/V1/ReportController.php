<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ExportRequest;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function attendance(Request $request): JsonResponse
    {
        $query = $this->scopedAttendance($request);

        return ApiResponder::success(
            $query->with('staff:id,first_name,last_name,name,employee_code')->paginate(50),
            'Attendance report generated'
        );
    }

    public function lateArrivals(Request $request): JsonResponse
    {
        $records = $this->scopedAttendance($request)
            ->where('late_minutes', '>', 0)
            ->with('staff:id,first_name,last_name,name,employee_code')
            ->orderByDesc('late_minutes')
            ->paginate(50);

        return ApiResponder::success($records, 'Late arrivals report generated');
    }

    public function absences(Request $request): JsonResponse
    {
        $records = $this->scopedAttendance($request)
            ->where('status', 'absent')
            ->with('staff:id,first_name,last_name,name,employee_code')
            ->paginate(50);

        return ApiResponder::success($records, 'Absences report generated');
    }

    public function overtime(Request $request): JsonResponse
    {
        $records = $this->scopedAttendance($request)
            ->where('overtime_minutes', '>', 0)
            ->with('staff:id,first_name,last_name,name,employee_code')
            ->orderByDesc('overtime_minutes')
            ->paginate(50);

        return ApiResponder::success($records, 'Overtime report generated');
    }

    public function leave(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['staff:id,first_name,last_name,name,employee_code', 'leaveType'])->latest();

        $user = $request->user();
        if (! $user->isAdmin()) {
            $teamIds = $user->staff?->subordinates()->pluck('id')->push($user->staff->id) ?? collect([-1]);
            $query->whereIn('staff_id', $teamIds);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->where('from_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('to_date', '<=', $to);
        }

        return ApiResponder::success($query->paginate(50), 'Leave report generated');
    }

    public function headcount(): JsonResponse
    {
        return ApiResponder::success([
            'total' => Staff::count(),
            'active' => Staff::where('employment_status', 'active')->count(),
            'by_branch' => Staff::selectRaw('branch_id, count(*) as total')
                ->where('employment_status', 'active')->groupBy('branch_id')->with('branch:id,name')->get(),
            'by_department' => Staff::selectRaw('department_id, count(*) as total')
                ->where('employment_status', 'active')->groupBy('department_id')->with('department:id,name')->get(),
            'by_status' => Staff::selectRaw('employment_status, count(*) as total')->groupBy('employment_status')->get(),
        ], 'Headcount report generated');
    }

    public function storeExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:attendance,late-arrivals,absences,overtime,leave,headcount'],
            'format' => ['nullable', 'in:csv'],
            'filters' => ['nullable', 'array'],
        ]);

        $export = ExportRequest::create([
            'user_id' => $request->user()->id,
            'report_type' => $validated['report_type'],
            'format' => $validated['format'] ?? 'csv',
            'filters' => $validated['filters'] ?? null,
            'status' => 'processing',
        ]);

        $rows = Attendance::with('staff:id,first_name,last_name,name,employee_code')
            ->latest('work_date')->limit(5000)->get();

        $csv = "work_date,employee_code,staff_name,status,check_in,check_out,worked_minutes,late_minutes\n";
        foreach ($rows as $row) {
            $csv .= implode(',', [
                $row->work_date?->toDateString(),
                $row->staff?->employee_code,
                '"'.str_replace('"', '""', $row->staff?->full_name ?? '').'"',
                $row->status,
                $row->check_in_at?->toIso8601String(),
                $row->check_out_at?->toIso8601String(),
                $row->worked_minutes,
                $row->late_minutes,
            ])."\n";
        }

        $path = "exports/attendance-{$export->id}.csv";
        Storage::put($path, $csv);
        $export->update(['status' => 'ready', 'file_path' => $path]);

        return ApiResponder::success($export, 'Export generated', 201);
    }

    public function showExport(Request $request, ExportRequest $export): JsonResponse
    {
        if ($export->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return ApiResponder::error('Not found.', 404);
        }

        return ApiResponder::success([
            'id' => $export->id,
            'report_type' => $export->report_type,
            'status' => $export->status,
            'download_url' => $export->file_path
                ? route('api.v1.reports.exports.download', $export)
                : null,
        ], 'Export retrieved');
    }

    public function downloadExport(Request $request, ExportRequest $export)
    {
        if ($export->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return ApiResponder::error('Not found.', 404);
        }

        if (! $export->file_path || ! Storage::exists($export->file_path)) {
            return ApiResponder::error('Export file is not available.', 404);
        }

        return Storage::download($export->file_path);
    }

    private function scopedAttendance(Request $request)
    {
        $query = Attendance::query()->latest('work_date');
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->hasPermissionTo('reports.view_all')) {
            $teamIds = $user->staff?->subordinates()->pluck('id')->push($user->staff->id) ?? collect([-1]);
            $query->whereIn('staff_id', $teamIds);
        }

        foreach (['staff_id', 'status'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }
        if ($from = $request->query('from')) {
            $query->where('work_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('work_date', '<=', $to);
        }

        return $query;
    }
}
