<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $documents = Document::where(fn ($q) => $q
            ->where('visibility', 'public')
            ->orWhere('owner_staff_id', $user->staff?->id ?? -1)
            ->when($user->isAdmin(), fn ($q2) => $q2->orWhereRaw('1=1')))
            ->latest()
            ->paginate(20);

        return ApiResponder::success($documents, 'Documents retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', 'in:private,staff,public'],
            'owner_staff_id' => ['nullable', 'exists:staff,id'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt', 'max:10240'],
        ]);

        $path = $request->file('file')->store('documents', 'local');

        $document = Document::create([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
            'owner_staff_id' => $validated['owner_staff_id'] ?? $request->user()->staff?->id,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponder::success($document->makeHidden('file_path'), 'Document uploaded', 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $document)) {
            return $denied;
        }

        return ApiResponder::success($document->makeHidden('file_path'), 'Document retrieved');
    }

    public function download(Request $request, Document $document)
    {
        if ($denied = $this->authorizeAccess($request, $document)) {
            return $denied;
        }

        if (! Storage::exists($document->file_path)) {
            return ApiResponder::error('File not available.', 404);
        }

        return Storage::download($document->file_path, $document->title);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $document)) {
            return $denied;
        }

        Storage::delete($document->file_path);
        $document->delete();

        return ApiResponder::success(null, 'Document deleted');
    }

    private function authorizeAccess(Request $request, Document $document): ?JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()
            || $document->visibility === 'public'
            || $document->owner_staff_id === $user->staff?->id
            || $document->uploaded_by === $user->id) {
            return null;
        }

        return ApiResponder::error('Not found.', 404);
    }
}
