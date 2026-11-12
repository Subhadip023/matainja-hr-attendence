<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    // Get all staff
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Staff::select('id', 'name', 'position')->get(),
        ]);
    }

    // Add new staff
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $staff = Staff::create([
            'name' => $request->name,
            'position' => $request->position,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff created successfully',
            'data' => $staff,
        ], 201);
    }
}
