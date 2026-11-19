<?php

namespace App\Http\Resources;

use App\Services\GeofenceService;
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
            'work_mode' => $this->status === 'wfh' ? 'wfh' : 'office',
            'check_in_location' => $this->whenLoaded('events', fn () => $this->punchLocation('check_in')),
            'check_out_location' => $this->whenLoaded('events', fn () => $this->punchLocation('check_out')),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'latitude' => $e->latitude,
                'longitude' => $e->longitude,
                'accuracy_meters' => $e->accuracy_meters,
            ])),
        ];
    }

    /**
     * Where the latest check-in / check-out punch happened, with the distance
     * from the staff member's branch office when both points are known.
     *
     * @return array<string, mixed>|null
     */
    private function punchLocation(string $eventType): ?array
    {
        $event = $this->events
            ->where('event_type', $eventType)
            ->sortByDesc('occurred_at')
            ->first();

        if (! $event || $event->latitude === null || $event->longitude === null) {
            return null;
        }

        $branch = $this->relationLoaded('staff') && $this->staff?->relationLoaded('branch')
            ? $this->staff->branch
            : null;

        $distance = null;
        if ($branch && $branch->latitude !== null && $branch->longitude !== null) {
            $distance = (int) round(app(GeofenceService::class)->distanceInMeters(
                (float) $branch->latitude,
                (float) $branch->longitude,
                (float) $event->latitude,
                (float) $event->longitude,
            ));
        }

        return [
            'latitude' => (float) $event->latitude,
            'longitude' => (float) $event->longitude,
            'accuracy_meters' => $event->accuracy_meters,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'distance_from_office_meters' => $distance,
            'office_name' => $branch?->name,
        ];
    }
}
