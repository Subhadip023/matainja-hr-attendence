<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(Shift::latest()->get(), 'Shifts retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'break_policy' => ['nullable', 'string', 'max:255'],
            'crosses_midnight' => ['boolean'],
        ]);

        return ApiResponder::success(Shift::create($validated), 'Shift created successfully', 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return ApiResponder::success($shift->load('assignments.staff:id,first_name,last_name,name'), 'Shift retrieved successfully');
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'break_policy' => ['nullable', 'string', 'max:255'],
            'crosses_midnight' => ['boolean'],
        ]);

        $shift->update($validated);

        return ApiResponder::success($shift, 'Shift updated successfully');
    }
}
