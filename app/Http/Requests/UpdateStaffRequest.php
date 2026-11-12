<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('employees.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'employee_code' => ['sometimes', 'string', 'max:50', Rule::unique('staff', 'employee_code')->ignore($staff)],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('staff', 'email')->ignore($staff)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'manager_id' => ['nullable', 'exists:staff,id', 'not_in:'.$staff?->id],
            'joining_date' => ['nullable', 'date'],
            'employment_status' => ['sometimes', 'in:active,probation,suspended,resigned,terminated'],
        ];
    }
}
