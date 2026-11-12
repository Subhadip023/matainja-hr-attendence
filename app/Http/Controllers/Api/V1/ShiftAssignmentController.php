<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffShiftAssignment;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    public function index(Request $request, Staff $staff): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $staff->id !== $user->staff?->id) {
            return ApiResponder::error('Not found.', 404);
        }

        return ApiResponder::success(
            $staff->shiftAssignments()->with('shift')->latest('effective_from')->get(),
            'Shift assignments retrieved'
        );
    }

    public function store(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        // Close the currently-open assignment before starting a new one.
        $staff->shiftAssignments()
            ->whereNull('effective_to')
            ->update(['effective_to' => $validated['effective_from']]);

        $assignment = $staff->shiftAssignments()->create($validated);

        return ApiResponder::success($assignment->load('shift'), 'Shift assigned successfully', 201);
    }

    public function destroy(StaffShiftAssignment $assignment): JsonResponse
    {
        $assignment->update(['effective_to' => now()->toDateString()]);

        return ApiResponder::success(null, 'Shift assignment ended');
    }
}
