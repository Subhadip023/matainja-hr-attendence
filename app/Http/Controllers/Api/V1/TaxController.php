<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tax;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    /** All taxes; ?active=1 for only the ones that can be put on an invoice. */
    public function index(Request $request): JsonResponse
    {
        $taxes = Tax::query()
            ->when($request->boolean('active'), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')->orderBy('name')->orderBy('rate')
            ->get();

        return ApiResponder::success($taxes, 'Taxes retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $tax = Tax::create($request->validate($this->rules($request)));
        AuditLog::record('taxes.created', $tax, null, $tax->toArray());

        return ApiResponder::success($tax->fresh(), 'Tax created', 201);
    }

    public function show(Tax $tax): JsonResponse
    {
        return ApiResponder::success($tax, 'Tax retrieved');
    }

    public function update(Request $request, Tax $tax): JsonResponse
    {
        $before = $tax->toArray();
        $tax->update($request->validate($this->rules($request, $tax)));
        AuditLog::record('taxes.updated', $tax, $before, $tax->toArray());

        return ApiResponder::success($tax, 'Tax updated');
    }

    /** Invoices keep their own copy of the tax, so deleting it never changes them. */
    public function destroy(Tax $tax): JsonResponse
    {
        $tax->delete();
        AuditLog::record('taxes.deleted', null, $tax->toArray());

        return ApiResponder::success(null, 'Tax deleted');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?Tax $tax = null): array
    {
        $required = $tax ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:100'],
            'rate' => [$required, 'numeric', 'min:0', 'max:100',
                Rule::unique('taxes', 'rate')->where('name', $request->input('name', $tax?->name))->ignore($tax)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
