<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\V1\AttendanceCorrectionController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\WfhController;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Leave, WFH and attendance-correction approvals. Decisions are delegated to
 * the API controllers so balances, audit logs and attendance fixes follow
 * exactly the same rules as approvals made in the mobile app.
 */
class ApprovalController extends AdminController
{
    public const KINDS = ['leave' => 'Leave', 'wfh' => 'Work from home', 'correction' => 'Attendance corrections'];

    public function index(Request $request): View
    {
        $kind = array_key_exists($request->query('kind'), self::KINDS) ? $request->query('kind') : 'leave';
        $status = $request->query('status', 'pending');

        $query = match ($kind) {
            'leave' => LeaveRequest::with(['staff.department:id,name', 'leaveType:id,name', 'approvals.approver:id,name']),
            'wfh' => WfhRequest::with(['staff.department:id,name', 'approver:id,name']),
            'correction' => AttendanceCorrectionRequest::with(['staff.department:id,name', 'attendance', 'reviewer:id,name']),
        };

        $items = $query
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'), fn ($q) => $q->where('status', '!=', 'pending'))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.approvals.index', [
            'kind' => $kind,
            'status' => $status,
            'items' => $items,
            'kinds' => self::KINDS,
            'pendingCounts' => [
                'leave' => LeaveRequest::where('status', 'pending')->count(),
                'wfh' => WfhRequest::where('status', 'pending')->count(),
                'correction' => AttendanceCorrectionRequest::where('status', 'pending')->count(),
            ],
            'tz' => $this->tz(),
        ]);
    }

    public function decide(Request $request, string $kind, int $id, string $decision): RedirectResponse
    {
        $request->validate([
            'comment' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000'],
        ], ['comment.required' => 'Please add a reason when rejecting.']);

        // The leave API reads "comments"; WFH and corrections read "comment".
        $request->merge(['comments' => $request->input('comment')]);

        $response = match ($kind) {
            'leave' => app(LeaveController::class)->{$decision}($request, LeaveRequest::findOrFail($id)),
            'wfh' => app(WfhController::class)->{$decision}($request, WfhRequest::findOrFail($id)),
            'correction' => app(AttendanceCorrectionController::class)->{$decision}($request, AttendanceCorrectionRequest::findOrFail($id)),
        };

        return $this->backFromApi($response, $decision === 'approve' ? 'Approved.' : 'Rejected.');
    }
}
