<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeIssueWithAI;
use App\Models\AuditLog;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssueController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'yacht_id' => 'nullable|integer|exists:yachts,id',
            'page_url' => 'nullable|string|max:2048',
            'browser' => 'nullable|string|max:255',
            'device' => 'nullable|string|max:255',
            'logs' => 'nullable',
        ]);

        $issue = Issue::create([
            'user_id' => $request->user()->id,
            'yacht_id' => $validated['yacht_id'] ?? null,
            'title' => $validated['title'] ?? Str::limit($validated['description'], 80, ''),
            'description' => $validated['description'],
            'status' => 'new',
            'page_url' => $validated['page_url'] ?? null,
            'browser' => $validated['browser'] ?? null,
            'device' => $validated['device'] ?? null,
            'logs' => $this->normalizeLogs($request->input('logs')),
            'screenshot_status' => 'none',
            'ai_status' => 'pending',
        ]);

        $this->audit($issue, 'issue.created', $request, [
            'has_screenshot' => $request->hasFile('screenshot'),
            'page_url' => $issue->page_url,
        ]);

        $screenshotFailed = false;

        if ($request->hasFile('screenshot')) {
            $screenshotFailed = ! $this->storeScreenshot($request, $issue);
        }

        AnalyzeIssueWithAI::dispatch($issue);
        $this->audit($issue, 'issue.ai_queued', $request);

        return response()->json([
            'message' => $this->successMessage($request, $screenshotFailed),
            'issue_id' => $issue->id,
            'status' => $issue->status,
            'ai_status' => $issue->ai_status,
            'screenshot_status' => $issue->screenshot_status,
        ], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Issue::query()
            ->with(['user:id,name,email,type,role', 'yacht:id,boat_name'])
            ->latest();

        $query->when($request->filled('status'), fn ($builder) => $builder->where('status', (string) $request->string('status')));
        $query->when($request->filled('ai_status'), fn ($builder) => $builder->where('ai_status', (string) $request->string('ai_status')));
        $query->when($request->filled('ai_priority'), fn ($builder) => $builder->where('ai_priority', (string) $request->string('ai_priority')));
        $query->when($request->filled('page_url'), fn ($builder) => $builder->where('page_url', 'like', '%' . (string) $request->string('page_url') . '%'));

        if ($request->boolean('has_screenshot')) {
            $query->where('screenshot_status', 'stored')->whereNotNull('screenshot_path');
        } elseif ($request->has('has_screenshot') && ! $request->boolean('has_screenshot')) {
            $query->where(function ($builder) {
                $builder->whereNull('screenshot_path')
                    ->orWhere('screenshot_status', '!=', 'stored');
            });
        }

        if ($request->filled('user_role')) {
            $role = strtoupper((string) $request->string('user_role'));
            $query->whereHas('user', fn ($builder) => $builder->where('type', $role));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('page_url', 'like', "%{$search}%");
            });
        }

        $issues = $query->paginate(min($request->integer('per_page', 15), 100));

        return response()->json([
            'data' => $issues->getCollection()->map(fn (Issue $issue) => $this->serializeIssue($issue))->values(),
            'current_page' => $issues->currentPage(),
            'per_page' => $issues->perPage(),
            'total' => $issues->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $issue = Issue::with(['user:id,name,email,type,role', 'yacht:id,boat_name'])->findOrFail($id);

        return response()->json(['data' => $this->serializeIssue($issue)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $issue = Issue::findOrFail($id);
        $before = $issue->only(['status', 'ai_priority']);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(Issue::STATUSES)],
            'ai_priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
        ]);

        $issue->fill(Arr::only($validated, ['status', 'ai_priority']))->save();

        $this->audit($issue, 'issue.status_changed', $request, [
            'before' => $before,
            'after' => $issue->only(['status', 'ai_priority']),
        ]);

        return response()->json(['data' => $this->serializeIssue($issue->fresh(['user:id,name,email,type,role', 'yacht:id,boat_name']))]);
    }

    public function retryAi(int $id): JsonResponse
    {
        $issue = Issue::findOrFail($id);
        $issue->update([
            'status' => 'ai_pending',
            'ai_status' => 'pending',
            'ai_error' => null,
        ]);

        AnalyzeIssueWithAI::dispatch($issue);
        $this->audit($issue, 'issue.ai_retried', request());

        return response()->json(['message' => 'AI analysis re-queued.']);
    }

    public function screenshot(int $id): StreamedResponse
    {
        $issue = Issue::findOrFail($id);

        abort_unless($issue->screenshot_path && Storage::disk('local')->exists($issue->screenshot_path), 404);

        return Storage::disk('local')->response(
            $issue->screenshot_path,
            $issue->screenshot_original_name ?: basename($issue->screenshot_path)
        );
    }

    private function storeScreenshot(Request $request, Issue $issue): bool
    {
        try {
            $file = $request->file('screenshot');

            if (! $file || ! $file->isValid()) {
                throw new \RuntimeException('Invalid screenshot upload.');
            }

            $extension = $file->extension() ?: 'png';
            $path = $file->storeAs(
                "issues/{$issue->id}",
                'screenshot.' . $extension,
                'local'
            );

            $issue->update([
                'screenshot_path' => $path,
                'screenshot_original_name' => $file->getClientOriginalName(),
                'screenshot_status' => 'stored',
            ]);

            $this->audit($issue, 'issue.screenshot_uploaded', $request, [
                'screenshot_path' => $path,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $issue->update([
                'screenshot_status' => 'failed',
            ]);

            $this->audit($issue, 'issue.screenshot_failed', $request, [
                'error' => $exception->getMessage(),
            ], 'FAILURE');

            return false;
        }
    }

    private function serializeIssue(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'status' => $issue->status,
            'title' => $issue->title,
            'description' => $issue->description,
            'page_url' => $issue->page_url,
            'browser' => $issue->browser,
            'device' => $issue->device,
            'logs' => $issue->logs ?? [],
            'screenshot_status' => $issue->screenshot_status,
            'screenshot_url' => $this->signedScreenshotUrl($issue),
            'ai_status' => $issue->ai_status,
            'ai_summary' => $issue->ai_summary,
            'ai_priority' => $issue->ai_priority,
            'ai_suggested_fix' => $issue->ai_suggested_fix,
            'ai_error' => $issue->ai_error,
            'ai_analyzed_at' => optional($issue->ai_analyzed_at)->toISOString(),
            'user' => $issue->user ? [
                'id' => $issue->user->id,
                'name' => $issue->user->name,
                'email' => $issue->user->email,
                'role' => $issue->user->role,
            ] : null,
            'yacht' => $issue->yacht ? [
                'id' => $issue->yacht->id,
                'boat_name' => $issue->yacht->boat_name,
            ] : null,
            'created_at' => optional($issue->created_at)->toISOString(),
            'updated_at' => optional($issue->updated_at)->toISOString(),
        ];
    }

    private function signedScreenshotUrl(Issue $issue): ?string
    {
        if ($issue->screenshot_status !== 'stored' || ! $issue->screenshot_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'admin.issues.screenshot',
            now()->addMinutes(15),
            ['id' => $issue->id]
        );
    }

    private function normalizeLogs(mixed $logs): array
    {
        if (is_array($logs)) {
            return $logs;
        }

        if (is_string($logs) && trim($logs) !== '') {
            $decoded = json_decode($logs, true);

            return is_array($decoded) ? $decoded : [$logs];
        }

        return [];
    }

    private function successMessage(Request $request, bool $screenshotFailed): string
    {
        $isDutch = str_starts_with(strtolower($request->header('Accept-Language', '')), 'nl')
            || strtolower((string) $request->input('locale')) === 'nl';

        if ($screenshotFailed) {
            return $isDutch
                ? 'Probleem opgeslagen, maar screenshot uploaden is mislukt.'
                : 'Issue saved, but screenshot upload failed.';
        }

        return $isDutch
            ? 'Probleem succesvol gemeld. We gaan het bekijken.'
            : 'Issue reported successfully. We will review it.';
    }

    private function audit(
        Issue $issue,
        string $action,
        ?Request $request = null,
        array $meta = [],
        string $result = 'SUCCESS'
    ): void {
        AuditLog::create([
            'action' => $action,
            'risk_level' => $result === 'SUCCESS' ? 'low' : 'medium',
            'result' => $result,
            'actor_id' => $request?->user()?->id,
            'target_type' => Issue::class,
            'target_id' => $issue->id,
            'entity_type' => Issue::class,
            'entity_id' => $issue->id,
            'meta' => $meta,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
