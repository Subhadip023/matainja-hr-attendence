<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only('id', 'name')),
            'staff_id' => $this->staff_id,
            'staff_name' => $this->whenLoaded('staff', fn () => $this->staff?->full_name),
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($c) => [
                'id' => $c->id,
                'user' => $c->user?->name,
                'comment' => $c->comment,
                'created_at' => $c->created_at?->toIso8601String(),
            ])),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'by' => $h->changer?->name,
                'reason' => $h->reason,
                'at' => $h->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
