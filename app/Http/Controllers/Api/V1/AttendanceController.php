<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PunchRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function checkIn(PunchRequest $request): JsonResponse
    {
        $attendance = $this->service->checkIn($request->user()->staff, $request->validated());

        return ApiResponder::success(
            new AttendanceResource($attendance->load('shift')),
            'Checked in successfully',
            201
        );
    }

    public function checkOut(PunchRequest $request): JsonResponse
    {
        $attendance = $this->service->checkOut($request->user()->staff, $request->validated());

        return ApiResponder::success(
            new AttendanceResource($attendance->load('shift')),
            'Checked out successfully'
        );
    }

    public function today(Request $request): JsonResponse
    {
        $staff = $request->user()->staff;
        $workDate = $this->service->workDate($staff, now()->toImmutable());

        $attendance = Attendance::with(['shift', 'openBreak', 'events'])
            ->where('staff_id', $staff->id)
            ->where('work_date', $workDate)
            ->first();

        return ApiResponder::success([
            'work_date' => $workDate,
            'attendance' => $attendance ? new AttendanceResource($attendance) : null,
            'shift' => $this->service->currentShiftAssignment($staff, $workDate)?->shift,
        ], "Today's attendance retrieved");
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with('staff:id,first_name,last_name,name')->latest('work_date');

        // Scope: employees see own, managers see team, HR/admin see all.
        if ($user->hasRole('Manager') && ! $user->isAdmin()) {
            $teamIds = $user->staff?->subordinates()->pluck('id') ?? collect([-1]);
            $query->whereIn('staff_id', $teamIds->push($user->staff?->id));
        } elseif (! $user->isAdmin() && ! $user->hasPermissionTo('attendance.view_all')) {
            $query->where('staff_id', $user->staff?->id ?? -1);
        }

        if ($staffId = $request->query('staff_id')) {
            $query->where('staff_id', $staffId);
        }
        if ($from = $request->query('from')) {
            $query->where('work_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('work_date', '<=', $to);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $records = $query->paginate($perPage);

        return ApiResponder::success(
            AttendanceResource::collection($records)->resolve(),
            'Attendance retrieved successfully',
            200,
            ['pagination' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ]]
        );
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()
            && $attendance->staff_id !== $user->staff?->id
            && $attendance->staff?->manager_id !== $user->staff?->id) {
            return ApiResponder::error('Not found.', 404);
        }

        return ApiResponder::success(
            new AttendanceResource($attendance->load(['staff', 'shift', 'events', 'breaks'])),
            'Attendance retrieved successfully'
        );
    }

    public function teamToday(Request $request): JsonResponse
    {
        $user = $request->user();
        $staffIds = $user->isAdmin()
            ? null
            : $user->staff?->subordinates()->pluck('id') ?? collect([-1]);

        $query = Attendance::with('staff:id,first_name,last_name,name')->where('work_date', now()->toDateString());

        if ($staffIds !== null) {
            $query->whereIn('staff_id', $staffIds);
        }

        return ApiResponder::success(
            AttendanceResource::collection($query->get())->resolve(),
            'Team attendance retrieved'
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $staffId = $request->query('staff_id', $user->staff?->id);

        if (! $user->isAdmin() && $staffId != $user->staff?->id) {
            $isSubordinate = $user->staff?->subordinates()->where('id', $staffId)->exists();
            if (! $isSubordinate) {
                return ApiResponder::error('You do not have permission to perform this action.', 403);
            }
        }

        $query = Attendance::where('staff_id', $staffId);

        if ($from = $request->query('from')) {
            $query->where('work_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('work_date', '<=', $to);
        }

        return ApiResponder::success([
            'total_days' => (clone $query)->count(),
            'present_days' => (clone $query)->whereIn('status', ['present', 'late'])->count(),
            'late_days' => (clone $query)->where('status', 'late')->count(),
            'absent_days' => (clone $query)->where('status', 'absent')->count(),
            'worked_minutes' => (clone $query)->sum('worked_minutes'),
            'overtime_minutes' => (clone $query)->sum('overtime_minutes'),
            'late_minutes' => (clone $query)->sum('late_minutes'),
        ], 'Attendance summary retrieved');
    }
}
