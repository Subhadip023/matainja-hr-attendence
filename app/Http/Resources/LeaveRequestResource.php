<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'staff_name' => $this->whenLoaded('staff', fn () => $this->staff?->full_name),
            'leave_type' => $this->whenLoaded('leaveType', fn () => $this->leaveType?->only('id', 'name', 'code')),
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'partial_day' => $this->partial_day,
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'status' => $this->status,
            'approvals' => $this->whenLoaded('approvals', fn () => $this->approvals->map(fn ($a) => [
                'id' => $a->id,
                'approver_id' => $a->approver_id,
                'approver_name' => $a->approver?->name,
                'decision' => $a->decision,
                'comments' => $a->comments,
                'decided_at' => $a->decided_at?->toIso8601String(),
                'step_order' => $a->step_order,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
