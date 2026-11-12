<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staffId = $request->user()?->staff?->id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'is_read' => $staffId !== null
                ? $this->reads->contains('staff_id', $staffId)
                : false,
        ];
    }
}
