<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WfhRequest;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WfhController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WfhRequest::with(['staff', 'approver:id,name'])->latest();

        if ($user->hasRole('Manager') && ! $user->isAdmin()) {
            $teamIds = $user->staff?->subordinates()->pluck('id')->push($user->staff->id) ?? collect([-1]);
            $query->whereIn('staff_id', $teamIds);
        } elseif (! $user->isAdmin()) {
            $query->where('staff_id', $user->staff?->id ?? -1);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponder::success($query->paginate(20), 'WFH requests retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $wfh = WfhRequest::create([
            'staff_id' => $request->user()->staff->id,
            ...$validated,
        ]);

        return ApiResponder::success($wfh, 'WFH request submitted', 201);
    }

    public function approve(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        return $this->decide($request, $wfhRequest, 'approved');
    }

    public function reject(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        return $this->decide($request, $wfhRequest, 'rejected');
    }

    private function decide(Request $request, WfhRequest $wfhRequest, string $decision): JsonResponse
    {
        if ($wfhRequest->status !== 'pending') {
            return ApiResponder::error('This request has already been decided.', 409);
        }

        $wfhRequest->update([
            'status' => $decision,
            'approver_id' => $request->user()->id,
            'approver_comment' => $request->input('comment'),
            'decided_at' => now(),
        ]);

        AuditLog::record("wfh.{$decision}", $wfhRequest);

        return ApiResponder::success($wfhRequest->fresh(), "WFH request {$decision}");
    }
}
