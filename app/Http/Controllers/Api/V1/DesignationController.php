<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(Designation::orderBy('level')->get(), 'Designations retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponder::success(Designation::create($validated), 'Designation created successfully', 201);
    }
}
