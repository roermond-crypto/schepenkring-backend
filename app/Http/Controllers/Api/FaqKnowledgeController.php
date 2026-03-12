<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqKnowledgeDocument;
use App\Models\FaqKnowledgeItem;
use App\Services\FaqKnowledgeIngestionService;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FaqKnowledgeController extends Controller
{
    public function __construct(
        private FaqKnowledgeIngestionService $knowledge,
        private LocationAccessService $locations
    ) {
    }

    public function upload(Request $request)
    {
        $user = $this->requireStaff($request);

        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'file' => 'required|file|max:20480|mimes:pdf,docx,xlsx,csv,txt,md',
            'language' => 'nullable|string|max:5',
            'category' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'visibility' => 'nullable|string|in:internal,staff,public',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'source_type' => 'nullable|string|max:40',
        ]);

        $this->authorizeLocation($user, (int) $validated['location_id']);

        $document = $this->knowledge->ingest($user, $request->file('file'), $validated);

        return response()->json([
            'message' => 'Knowledge document processed',
            'document' => $document,
            'items' => $document->items,
        ], 201);
    }

    public function documents(Request $request)
    {
        $user = $this->requireStaff($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'status' => 'nullable|string|in:uploaded,processing,pending_review,failed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = FaqKnowledgeDocument::query()->withCount('items')->latest('id');
        $query = $this->scopeQuery($query, $user, 'location_id');

        if (! empty($validated['location_id'])) {
            $this->authorizeLocation($user, (int) $validated['location_id']);
            $query->where('location_id', (int) $validated['location_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 25)));
    }

    public function items(Request $request)
    {
        $user = $this->requireStaff($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'document_id' => 'nullable|integer|exists:faq_knowledge_documents,id',
            'status' => 'nullable|string|in:pending,approved,declined',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = FaqKnowledgeItem::query()
            ->with(['document', 'approvedFaq'])
            ->latest('id');
        $query = $this->scopeQuery($query, $user, 'location_id');

        if (! empty($validated['location_id'])) {
            $this->authorizeLocation($user, (int) $validated['location_id']);
            $query->where('location_id', (int) $validated['location_id']);
        }

        if (! empty($validated['document_id'])) {
            $query->where('document_id', (int) $validated['document_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 25)));
    }

    public function review(Request $request, FaqKnowledgeItem $item)
    {
        $user = $this->requireStaff($request);
        $this->authorizeLocation($user, $item->location_id);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,approved,declined',
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'language' => 'sometimes|nullable|string|max:5',
            'category' => 'sometimes|nullable|string|max:100',
            'department' => 'sometimes|nullable|string|max:100',
            'visibility' => 'sometimes|nullable|string|in:internal,staff,public',
            'brand' => 'sometimes|nullable|string|max:100',
            'model' => 'sometimes|nullable|string|max:100',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
            'review_notes' => 'sometimes|nullable|string',
        ]);

        $item = $this->knowledge->review($user, $item, $validated);

        return response()->json([
            'message' => 'Knowledge item reviewed',
            'item' => $item,
        ]);
    }

    public function destroy(Request $request, FaqKnowledgeItem $item)
    {
        $user = $this->requireStaff($request);
        $this->authorizeLocation($user, $item->location_id);

        $this->knowledge->deleteItem($item);

        return response()->json(['message' => 'deleted']);
    }

    public function analytics(Request $request)
    {
        $user = $this->requireStaff($request);

        return response()->json($this->knowledge->analyticsFor($user, $this->locations));
    }

    private function requireStaff(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthorized');
        }
        if (! $user->isStaff()) {
            abort(403, 'Forbidden');
        }

        return $user;
    }

    private function authorizeLocation($user, ?int $locationId): void
    {
        if (! $locationId || ! $this->locations->sharesLocation($user, $locationId)) {
            abort(403, 'Forbidden');
        }
    }

    private function scopeQuery(Builder $query, $user, string $locationColumn = 'location_id'): Builder
    {
        return $this->locations->scopeQuery($query, $user, $locationColumn);
    }
}
