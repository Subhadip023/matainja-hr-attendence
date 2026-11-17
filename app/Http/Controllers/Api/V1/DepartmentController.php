<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\ApiResponder;
use App\Support\ReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(
            ReferenceCache::get('departments', fn () => Department::with('head:id,first_name,last_name,name')->latest()->get()),
            'Departments retrieved successfully'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'head_staff_id' => ['nullable', 'exists:staff,id'],
        ]);

        return ApiResponder::success(Department::create($validated), 'Department created successfully', 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'head_staff_id' => ['nullable', 'exists:staff,id'],
        ]);

        $department->update($validated);

        return ApiResponder::success($department, 'Department updated successfully');
    }
}
