<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AuditLog;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AttendanceCorrectionRequest::with(['staff', 'reviewer:id,name'])->latest();

        if (! $user->isAdmin()) {
            $teamIds = $user->hasRole('Manager')
                ? $user->staff?->subordinates()->pluck('id')->push($user->staff->id) ?? collect([-1])
                : collect([$user->staff?->id ?? -1]);
            $query->whereIn('staff_id', $teamIds);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponder::success($query->paginate(20), 'Correction requests retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'attendance_id' => ['nullable', 'exists:attendance,id'],
            'requested_values' => ['required', 'array'],
            'requested_values.check_in_at' => ['nullable', 'date'],
            'requested_values.check_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $correction = AttendanceCorrectionRequest::create([
            'staff_id' => $request->user()->staff->id,
            'attendance_id' => $validated['attendance_id'] ?? null,
            'work_date' => $validated['work_date'],
            'requested_values' => $validated['requested_values'],
            'reason' => $validated['reason'],
        ]);

        return ApiResponder::success($correction, 'Correction request submitted', 201);
    }

    public function approve(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        if ($correction->status !== 'pending') {
            return ApiResponder::error('This request has already been reviewed.', 409);
        }

        DB::transaction(function () use ($correction, $request) {
            $correction->update([
                'status' => 'approved',
                'reviewer_id' => $request->user()->id,
                'review_comment' => $request->input('comment'),
                'reviewed_at' => now(),
            ]);

            // Apply the correction — original events are preserved in attendance_events.
            $attendance = $correction->attendance
                ?? Attendance::firstOrCreate([
                    'staff_id' => $correction->staff_id,
                    'work_date' => $correction->work_date,
                ], ['source' => 'correction']);

            $values = array_filter($correction->requested_values ?? []);
            $attendance->update(array_merge($values, ['source' => 'correction']));

            $attendance->events()->create([
                'event_type' => 'correction',
                'occurred_at' => now(),
                'metadata' => ['correction_request_id' => $correction->id, 'applied' => $values],
            ]);
        });

        AuditLog::record('attendance.correction_approved', $correction);

        return ApiResponder::success($correction->fresh(), 'Correction approved');
    }

    public function reject(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        if ($correction->status !== 'pending') {
            return ApiResponder::error('This request has already been reviewed.', 409);
        }

        $correction->update([
            'status' => 'rejected',
            'reviewer_id' => $request->user()->id,
            'review_comment' => $request->input('comment'),
            'reviewed_at' => now(),
        ]);

        AuditLog::record('attendance.correction_rejected', $correction);

        return ApiResponder::success($correction->fresh(), 'Correction rejected');
    }
}
