<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PunchRequest extends FormRequest
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
