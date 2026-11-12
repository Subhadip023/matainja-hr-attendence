<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingRequest;
use App\Models\Meeting;
use App\Models\MeetingRoom;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    public function rooms(Request $request): JsonResponse
    {
        $query = MeetingRoom::query();

        if ($branchId = $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        return ApiResponder::success($query->get(), 'Meeting rooms retrieved');
    }

    public function index(Request $request): JsonResponse
    {
        $staffId = $request->user()->staff?->id;

        $meetings = Meeting::with(['organiser', 'room', 'participants.staff'])
            ->where('status', 'scheduled')
            ->where(fn ($q) => $q->where('organiser_id', $staffId)
                ->orWhereHas('participants', fn ($q2) => $q2->where('staff_id', $staffId)))
            ->when($request->user()->isAdmin() && $request->boolean('all'), fn ($q) => $q->orWhereRaw('1=1'))
            ->upcoming()
            ->latest('starts_at')
            ->paginate(20);

        return ApiResponder::success($meetings, 'Meetings retrieved');
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $meeting = DB::transaction(function () use ($validated, $request) {
            $meeting = Meeting::create([
                'organiser_id' => $request->user()->staff->id,
                'title' => $validated['title'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'room_id' => $validated['room_id'] ?? null,
                'meeting_url' => $validated['meeting_url'] ?? null,
            ]);

            foreach ($validated['participant_ids'] ?? [] as $staffId) {
                $meeting->participants()->create(['staff_id' => $staffId]);
            }

            return $meeting;
        });

        return ApiResponder::success(
            $meeting->load(['organiser', 'room', 'participants.staff']),
            'Meeting created',
            201
        );
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $meeting)) {
            return $denied;
        }

        return ApiResponder::success(
            $meeting->load(['organiser', 'room', 'participants.staff']),
            'Meeting retrieved'
        );
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        if ($meeting->organiser_id !== $request->user()->staff?->id && ! $request->user()->isAdmin()) {
            return ApiResponder::error('You do not have permission to perform this action.', 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at'],
            'room_id' => ['nullable', 'exists:meeting_rooms,id'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
        ]);

        $meeting->update($validated);

        return ApiResponder::success($meeting->fresh(), 'Meeting updated');
    }

    public function cancel(Request $request, Meeting $meeting): JsonResponse
    {
        if ($meeting->organiser_id !== $request->user()->staff?->id && ! $request->user()->isAdmin()) {
            return ApiResponder::error('You do not have permission to perform this action.', 403);
        }

        if ($meeting->status !== 'scheduled') {
            return ApiResponder::error('Meeting is already cancelled.', 409);
        }

        $meeting->update(['status' => 'cancelled']);

        return ApiResponder::success(null, 'Meeting cancelled');
    }

    public function respond(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'in:accepted,declined,tentative'],
        ]);

        $participant = $meeting->participants()
            ->where('staff_id', $request->user()->staff?->id)
            ->first();

        if (! $participant) {
            return ApiResponder::error('Not found.', 404);
        }

        $participant->update(['response_status' => $validated['response']]);

        return ApiResponder::success($participant, 'Response recorded');
    }

    private function authorizeAccess(Request $request, Meeting $meeting): ?JsonResponse
    {
        $staffId = $request->user()->staff?->id;

        if ($request->user()->isAdmin()
            || $meeting->organiser_id === $staffId
            || $meeting->participants()->where('staff_id', $staffId)->exists()) {
            return null;
        }

        return ApiResponder::error('Not found.', 404);
    }
}
