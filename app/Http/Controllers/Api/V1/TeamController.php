<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(
            Team::with(['department:id,name', 'manager:id,first_name,last_name,name'])->latest()->get(),
            'Teams retrieved successfully'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:staff,id'],
        ]);

        return ApiResponder::success(Team::create($validated), 'Team created successfully', 201);
    }
}
