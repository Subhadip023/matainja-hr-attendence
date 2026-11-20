<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WfhRequest;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    public function index(): View
    {
        $today = $this->today()->toDateString();

        $statusCounts = Attendance::whereDate('work_date', $today)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $activeStaff = Staff::whereIn('employment_status', ['active', 'probation'])->count();
        $present = (int) ($statusCounts['present'] ?? 0) + (int) ($statusCounts['late'] ?? 0) + (int) ($statusCounts['wfh'] ?? 0);
        $onLeave = LeaveRequest::where('status', 'approved')
            ->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->count();

        $pending = [
            'leave' => LeaveRequest::where('status', 'pending')->count(),
            'wfh' => WfhRequest::where('status', 'pending')->count(),
            'correction' => AttendanceCorrectionRequest::where('status', 'pending')->count(),
        ];

        // Last 7 days of check-ins for the trend bars.
        $from = $this->today()->subDays(6);
        $daily = Attendance::whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $today)
            ->whereIn('status', ['present', 'late', 'wfh'])
            ->selectRaw('DATE(work_date) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $trend = collect(range(0, 6))->map(function ($i) use ($from, $daily) {
            $day = $from->addDays($i);

            return ['label' => $day->format('D'), 'date' => $day->format('d M'), 'count' => (int) ($daily[$day->toDateString()] ?? 0)];
        });

        $byDepartment = Department::withCount(['staff' => fn ($q) => $q->whereIn('employment_status', ['active', 'probation'])])
            ->orderByDesc('staff_count')->take(6)->get();

        return view('admin.dashboard', [
            'today' => $this->today(),
            'stats' => [
                'active_staff' => $activeStaff,
                'present' => $present,
                'wfh' => (int) ($statusCounts['wfh'] ?? 0),
                'late' => (int) ($statusCounts['late'] ?? 0),
                'on_leave' => $onLeave,
                'not_in' => max(0, $activeStaff - $present - $onLeave),
                'users' => User::count(),
                'branches' => Branch::count(),
                'open_tickets' => Ticket::whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
            'pending' => $pending,
            'trend' => $trend,
            'byDepartment' => $byDepartment,
            'recentLogs' => AuditLog::with('actor:id,name')->latest()->take(8)->get(),
        ]);
    }
}
