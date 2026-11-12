<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Asset::with('currentAssignment.staff:id,first_name,last_name,name')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponder::success($query->paginate(20), 'Assets retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_tag' => ['required', 'string', 'max:100', 'unique:assets,asset_tag'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponder::success(Asset::create($validated), 'Asset registered', 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        return ApiResponder::success(
            $asset->load(['currentAssignment.staff', 'assignments.staff']),
            'Asset retrieved'
        );
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:available,assigned,maintenance,retired'],
        ]);

        $asset->update($validated);

        return ApiResponder::success($asset, 'Asset updated');
    }

    public function assign(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($asset->currentAssignment) {
            return ApiResponder::error('Asset is already assigned.', 409);
        }

        $assignment = DB::transaction(function () use ($asset, $validated) {
            $assignment = $asset->assignments()->create([
                'staff_id' => $validated['staff_id'],
                'assigned_at' => now(),
                'condition_notes' => $validated['condition_notes'] ?? null,
            ]);
            $asset->update(['status' => 'assigned']);

            return $assignment;
        });

        AuditLog::record('asset.assigned', $asset);

        return ApiResponder::success($assignment, 'Asset assigned', 201);
    }

    public function returnAsset(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'condition_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = $asset->currentAssignment;

        if (! $assignment) {
            return ApiResponder::error('Asset is not currently assigned.', 409);
        }

        DB::transaction(function () use ($assignment, $asset, $validated) {
            $assignment->update([
                'returned_at' => now(),
                'condition_notes' => $validated['condition_notes'] ?? $assignment->condition_notes,
            ]);
            $asset->update(['status' => 'available']);
        });

        AuditLog::record('asset.returned', $asset);

        return ApiResponder::success($assignment->fresh(), 'Asset returned');
    }

    public function staffAssets(Request $request, Staff $staff): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $staff->id !== $user->staff?->id) {
            return ApiResponder::error('Not found.', 404);
        }

        return ApiResponder::success(
            $staff->assetAssignments()->with('asset')->whereNull('returned_at')->get(),
            'Assigned assets retrieved'
        );
    }
}
