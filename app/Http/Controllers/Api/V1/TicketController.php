<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function categories(): JsonResponse
    {
        return ApiResponder::success(TicketCategory::all(), 'Ticket categories retrieved');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::with(['category', 'staff', 'assignee:id,name'])->latest();

        if (! $user->isAdmin() && ! $user->hasPermissionTo('tickets.manage')) {
            $query->where(fn ($q) => $q
                ->where('staff_id', $user->staff?->id ?? -1)
                ->orWhere('assigned_to', $user->id));
        }

        foreach (['status', 'priority', 'category_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        $tickets = $query->paginate(20);

        return ApiResponder::success(
            TicketResource::collection($tickets)->resolve(),
            'Tickets retrieved'
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $ticket = DB::transaction(function () use ($validated, $request) {
            $ticket = Ticket::create([
                'ticket_number' => 'TKT-'.now()->format('Ymd').'-'.str_pad((string) (Ticket::max('id') + 1), 5, '0', STR_PAD_LEFT),
                'staff_id' => $request->user()->staff->id,
                'category_id' => $validated['category_id'] ?? null,
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => $validated['priority'] ?? 'medium',
            ]);

            $ticket->statusHistories()->create([
                'from_status' => null,
                'to_status' => 'open',
                'changed_by' => $request->user()->id,
            ]);

            return $ticket;
        });

        return ApiResponder::success(new TicketResource($ticket->load('category')), 'Ticket created', 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $ticket)) {
            return $denied;
        }

        return ApiResponder::success(
            new TicketResource($ticket->load(['category', 'staff', 'assignee', 'comments.user', 'statusHistories.changer'])),
            'Ticket retrieved'
        );
    }

    public function comment(Request $request, Ticket $ticket): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $ticket)) {
            return $denied;
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $validated['comment'],
        ]);

        return ApiResponder::success($comment->load('user:id,name'), 'Comment added', 201);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $this->transition($ticket, 'assigned', $request, 'Assigned to agent');
        $ticket->update(['assigned_to' => $validated['assigned_to']]);

        return ApiResponder::success(new TicketResource($ticket->fresh()->load('assignee')), 'Ticket assigned');
    }

    public function changeStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:assigned,in_progress,waiting_for_employee,resolved'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $ticket->canTransitionTo($validated['status'])) {
            return ApiResponder::error(
                "Cannot transition from {$ticket->status} to {$validated['status']}.",
                409
            );
        }

        $this->transition($ticket, $validated['status'], $request, $validated['reason'] ?? null);

        return ApiResponder::success(new TicketResource($ticket->fresh()), 'Status updated');
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        if (! $ticket->canTransitionTo('closed')) {
            return ApiResponder::error('This ticket cannot be closed from its current state.', 409);
        }

        $this->transition($ticket, 'closed', $request, $request->input('reason'));

        return ApiResponder::success(new TicketResource($ticket->fresh()), 'Ticket closed');
    }

    private function transition(Ticket $ticket, string $to, Request $request, ?string $reason): void
    {
        DB::transaction(function () use ($ticket, $to, $request, $reason) {
            $from = $ticket->status;
            $ticket->update(['status' => $to]);
            $ticket->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $request->user()->id,
                'reason' => $reason,
            ]);
        });
    }

    private function authorizeAccess(Request $request, Ticket $ticket): ?JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()
            || $user->hasPermissionTo('tickets.manage')
            || $ticket->staff_id === $user->staff?->id
            || $ticket->assigned_to === $user->id) {
            return null;
        }

        return ApiResponder::error('Not found.', 404);
    }
}
