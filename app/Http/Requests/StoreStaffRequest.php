<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('employees.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:50', 'unique:staff,employee_code'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:staff,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'manager_id' => ['nullable', 'exists:staff,id'],
            'joining_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'in:active,probation,suspended,resigned,terminated'],
            'create_login' => ['boolean'],
            'role' => ['nullable', 'string', 'exists:roles,name', 'required_with:create_login'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
