<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->staff !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'room_id' => ['nullable', 'exists:meeting_rooms,id'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['exists:staff,id'],
        ];
    }
}
