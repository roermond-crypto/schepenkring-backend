<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\FaqTrainingService;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function __construct(
        private FaqTrainingService $training,
        private LocationAccessService $locations
    ) {
    }

    public function index(Request $request)
    {
        $user = $this->requireStaff($request);

        $validated = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Faq::query()->orderBy('question');
        $query = $this->scopeFaqs($query, $user);

        if (! empty($validated['location_id'])) {
            $this->authorizeLocation($user, (int) $validated['location_id']);
            $query->where('location_id', (int) $validated['location_id']);
        }

        if (! empty($validated['search'])) {
            $search = '%' . trim((string) $validated['search']) . '%';
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('question', 'like', $search)
                    ->orWhere('answer', 'like', $search)
                    ->orWhere('category', 'like', $search);
            });
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 25)));
    }

    public function store(Request $request)
    {
        $user = $this->requireStaff($request);

        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:100',
        ]);

        $this->authorizeLocation($user, (int) $validated['location_id']);

        $faq = $this->training->upsertFaq(
            (int) $validated['location_id'],
            $validated['question'],
            $validated['answer'],
            $validated['category'] ?? null,
            null,
            $user
        );

        return response()->json($faq, 201);
    }

    public function update(Request $request, Faq $faq)
    {
        $user = $this->requireStaff($request);

        $this->authorizeLocation($user, $faq->location_id);

        $validated = $request->validate([
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'category' => 'sometimes|nullable|string|max:100',
        ]);

        $faq = $this->training->upsertFaq(
            (int) $faq->location_id,
            $validated['question'] ?? $faq->question,
            $validated['answer'] ?? $faq->answer,
            $validated['category'] ?? $faq->category,
            $faq->source_message_id,
            $user
        );

        return response()->json($faq);
    }

    public function destroy(Request $request, Faq $faq)
    {
        $user = $this->requireStaff($request);

        $this->authorizeLocation($user, $faq->location_id);
        $this->training->deleteFaq($faq);

        return response()->json(['message' => 'deleted']);
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

    private function scopeFaqs(Builder $query, $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $locationIds = $this->locations->accessibleLocationIds($user);
        if ($locationIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('location_id', $locationIds);
    }
}
