<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Ticket;
use App\Services\AttendanceService;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function employee(Request $request): JsonResponse
    {
        $staff = $request->user()->staff;

        if (! $staff) {
            return ApiResponder::error('No staff profile linked to this account.', 404);
        }

        $workDate = $this->attendanceService->workDate($staff, now()->toImmutable());

        return ApiResponder::success([
            'today_attendance' => Attendance::with('openBreak')
                ->where('staff_id', $staff->id)->where('work_date', $workDate)->first(),
            'shift' => $this->attendanceService->currentShiftAssignment($staff, $workDate)?->shift,
            'leave_balances' => LeaveBalance::with('leaveType')
                ->where('staff_id', $staff->id)->where('year', now()->year)->get(),
            'pending_leave_requests' => LeaveRequest::where('staff_id', $staff->id)
                ->where('status', 'pending')->count(),
            'open_tickets' => Ticket::where('staff_id', $staff->id)
                ->whereNotIn('status', ['resolved', 'closed'])->count(),
            'announcements' => Announcement::whereNotNull('published_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('published_at')->limit(5)->get(['id', 'title', 'published_at']),
        ], 'Dashboard retrieved');
    }

    public function manager(Request $request): JsonResponse
    {
        $staff = $request->user()->staff;
        $teamIds = $staff?->subordinates()->pluck('id') ?? collect();

        $today = now()->toDateString();
        $statusCounts = Attendance::whereIn('staff_id', $teamIds)
            ->where('work_date', $today)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return ApiResponder::success([
            'team_size' => $teamIds->count(),
            'present_today' => (int) ($statusCounts['present'] ?? 0) + (int) ($statusCounts['late'] ?? 0) + (int) ($statusCounts['wfh'] ?? 0),
            'wfh_today' => (int) ($statusCounts['wfh'] ?? 0),
            'late_today' => (int) ($statusCounts['late'] ?? 0),
            'absent_today' => $teamIds->count() - (int) $statusCounts->sum(),
            'pending_leave_approvals' => LeaveApproval::where('approver_id', $request->user()->id)
                ->whereNull('decision')->count(),
        ], 'Manager dashboard retrieved');
    }

    public function hr(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $statusCounts = Attendance::where('work_date', $today)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return ApiResponder::success([
            'headcount' => Staff::where('employment_status', 'active')->count(),
            'present_today' => (int) ($statusCounts['present'] ?? 0) + (int) ($statusCounts['late'] ?? 0) + (int) ($statusCounts['wfh'] ?? 0),
            'wfh_today' => (int) ($statusCounts['wfh'] ?? 0),
            'late_today' => (int) ($statusCounts['late'] ?? 0),
            'on_leave_today' => LeaveRequest::where('status', 'approved')
                ->where('from_date', '<=', $today)->where('to_date', '>=', $today)->count(),
            'pending_leave_requests' => LeaveRequest::where('status', 'pending')->count(),
            'pending_corrections' => AttendanceCorrectionRequest::where('status', 'pending')->count(),
            'open_tickets' => Ticket::whereNotIn('status', ['resolved', 'closed'])->count(),
        ], 'HR dashboard retrieved');
    }
}
