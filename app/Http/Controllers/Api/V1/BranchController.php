<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\ApiResponder;
use App\Support\ReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(
            ReferenceCache::get('branches', fn () => Branch::latest()->get()),
            'Branches retrieved successfully'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'timezone'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $branch = Branch::create($validated);

        return ApiResponder::success($branch, 'Branch created successfully', 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'timezone'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $branch->update($validated);

        return ApiResponder::success($branch, 'Branch updated successfully');
    }
}
