<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreakController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function index(Request $request, Attendance $attendance): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $attendance)) {
            return $denied;
        }

        return ApiResponder::success(
            $attendance->breaks()->latest('started_at')->get(),
            'Breaks retrieved successfully'
        );
    }

    public function start(Request $request, Attendance $attendance): JsonResponse
    {
        $request->validate(['break_type' => ['nullable', 'string', 'max:50']]);

        if ($denied = $this->authorizeOwner($request, $attendance)) {
            return $denied;
        }

        $break = $this->service->startBreak($attendance, $request->input('break_type', 'general'));

        return ApiResponder::success($break, 'Break started', 201);
    }

    public function end(Request $request, Attendance $attendance): JsonResponse
    {
        if ($denied = $this->authorizeOwner($request, $attendance)) {
            return $denied;
        }

        $break = $this->service->endBreak($attendance);

        return ApiResponder::success($break, 'Break ended');
    }

    private function authorizeOwner(Request $request, Attendance $attendance): ?JsonResponse
    {
        if ($attendance->staff_id !== $request->user()->staff?->id) {
            return ApiResponder::error('Not found.', 404);
        }

        return null;
    }

    private function authorizeAccess(Request $request, Attendance $attendance): ?JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()
            || $attendance->staff_id === $user->staff?->id
            || $attendance->staff?->manager_id === $user->staff?->id) {
            return null;
        }

        return ApiResponder::error('Not found.', 404);
    }
}
