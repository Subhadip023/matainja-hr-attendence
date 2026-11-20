<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::orderBy('name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%"));
        }

        return ApiResponder::success($query->paginate(20), 'Clients retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $client = Client::create($request->validate($this->rules()));
        AuditLog::record('clients.created', $client, null, $client->toArray());

        return ApiResponder::success($client->fresh(), 'Client created', 201);
    }

    public function show(Client $client): JsonResponse
    {
        return ApiResponder::success($client, 'Client retrieved');
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $before = $client->toArray();
        $client->update($request->validate($this->rules($client)));
        AuditLog::record('clients.updated', $client, $before, $client->toArray());

        return ApiResponder::success($client, 'Client updated');
    }

    public function destroy(Client $client): JsonResponse
    {
        if ($client->invoices()->exists()) {
            return ApiResponder::error('This client has invoices and can\'t be deleted. Mark it inactive instead.', 422);
        }
        $client->delete();
        AuditLog::record('clients.deleted', null, $client->toArray());

        return ApiResponder::success(null, 'Client deleted');
    }

    /** @return array<string, mixed> */
    private function rules(?Client $client = null): array
    {
        $required = $client ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'code' => [$required, 'string', 'max:50', Rule::unique('clients', 'code')->ignore($client)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
