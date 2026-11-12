<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:ticket_categories,id'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
