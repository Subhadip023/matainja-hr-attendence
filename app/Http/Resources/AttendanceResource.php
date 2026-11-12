<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'work_date' => $this->work_date?->toDateString(),
            'shift' => $this->whenLoaded('shift', fn () => $this->shift?->only('id', 'name', 'start_time', 'end_time')),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'worked_minutes' => $this->worked_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'source' => $this->source,
            'active_break' => $this->whenLoaded('openBreak', fn () => $this->openBreak ? [
                'id' => $this->openBreak->id,
                'break_type' => $this->openBreak->break_type,
                'started_at' => $this->openBreak->started_at?->toIso8601String(),
            ] : null),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'latitude' => $e->latitude,
                'longitude' => $e->longitude,
            ])),
        ];
    }
}
