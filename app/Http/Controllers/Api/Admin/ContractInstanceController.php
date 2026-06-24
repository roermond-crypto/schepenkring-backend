<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractInstance;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\SignRequest;
use App\Services\ContractTemplateRendererService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContractInstanceController extends Controller
{
    public function __construct(
        private readonly ContractTemplateRendererService $renderer,
    ) {}

    // ── GET /api/admin/contract-instances/{signRequestId} ────────────────────

    public function show(int $signRequestId): JsonResponse
    {
        $instance = ContractInstance::where('sign_request_id', $signRequestId)->first();

        if ($instance) {
            $tags = [];
            $missingTags = [];

            if ($instance->yacht_id) {
                $tags = $this->renderer->resolveTagsForYacht($instance->yacht_id, $signRequestId);
                $missingTags = $this->renderer->getMissingTags($instance->content_html, array_keys($tags));
            }

            return response()->json([
                'instance'     => $instance->load(['template:id,name,type', 'signRequest:id,location_id,entity_type,entity_id,status']),
                'tags'         => $tags,
                'missing_tags' => $missingTags,
            ]);
        }

        // Instance not found — create one from the default template
        $signRequest = SignRequest::find($signRequestId);

        if (! $signRequest) {
            return response()->json(['message' => 'Sign request niet gevonden.'], 404);
        }

        $locationId = $signRequest->location_id;
        $type = 'purchase_contract';  // default type

        // Find the best default template for this location+type
        $template = null;

        if ($locationId) {
            $template = ContractTemplate::where('location_id', $locationId)
                ->where('type', $type)
                ->where('is_default', true)
                ->where('is_archived', false)
                ->first();

            if (! $template) {
                $template = ContractTemplate::where('location_id', $locationId)
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->where('is_archived', false)
                    ->first();
            }
        }

        if (! $template) {
            $template = ContractTemplate::whereNull('location_id')
                ->where('type', $type)
                ->where('is_default', true)
                ->where('is_archived', false)
                ->first();
        }

        if (! $template) {
            $template = ContractTemplate::whereNull('location_id')
                ->where('type', $type)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->first();
        }

        // Determine yacht_id from the sign request
        $yachtId = null;
        if ($signRequest->entity_type === 'yacht' || $signRequest->entity_type === 'App\\Models\\Yacht') {
            $yachtId = $signRequest->entity_id;
        }

        // Resolve real tags
        $tags = [];
        if ($yachtId) {
            $tags = $this->renderer->resolveTagsForYacht($yachtId, $signRequestId);
        }

        // Create a new ContractInstance from the template (or empty)
        $instance = new ContractInstance([
            'sign_request_id'       => $signRequestId,
            'contract_template_id'  => $template?->id,
            'template_version_used' => $template?->current_version,
            'content_html'          => $template?->content_html ?? '',
            'content_json'          => $template?->content_json,
            'yacht_id'              => $yachtId,
            'location_id'           => $locationId,
            'tags_data'             => $tags,
        ]);

        // Don't persist yet — return in-memory instance so the UI can review before saving
        $missingTags = $this->renderer->getMissingTags($instance->content_html, array_keys($tags));

        return response()->json([
            'instance'     => $instance,
            'tags'         => $tags,
            'missing_tags' => $missingTags,
        ], 200);
    }

    // ── PUT /api/admin/contract-instances/{signRequestId} ────────────────────

    public function update(Request $request, int $signRequestId): JsonResponse
    {
        $data = $request->validate([
            'content_html'    => 'required|string',
            'content_json'    => 'nullable|array',
            'action'          => 'required|string|in:save_instance_only,save_as_current_template,save_as_new_template',
            'change_note'     => 'nullable|string|max:500',
            // Only required when action=save_as_new_template
            'name'            => 'nullable|string|max:255',
            'type'            => 'nullable|string|max:100',
            'language'        => 'nullable|string|in:nl,en,de,fr',
            'location_id'     => 'nullable|integer|exists:locations,id',
            'is_default'      => 'nullable|boolean',
        ]);

        if ($data['action'] === 'save_as_new_template') {
            $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|string|max:100',
            ]);
        }

        $signRequest = SignRequest::find($signRequestId);
        if (! $signRequest) {
            return response()->json(['message' => 'Sign request niet gevonden.'], 404);
        }

        // Determine yacht_id
        $yachtId = null;
        if ($signRequest->entity_type === 'yacht' || $signRequest->entity_type === 'App\\Models\\Yacht') {
            $yachtId = $signRequest->entity_id;
        }

        // Find or create the ContractInstance
        $instance = ContractInstance::where('sign_request_id', $signRequestId)->first();

        if (! $instance) {
            $instance = new ContractInstance([
                'sign_request_id' => $signRequestId,
                'yacht_id'        => $yachtId,
                'location_id'     => $signRequest->location_id,
                'created_by_id'   => $request->user()?->id,
            ]);
        }

        // Always update the instance content
        $instance->content_html  = $data['content_html'];
        $instance->content_json  = $data['content_json'] ?? $instance->content_json;
        $instance->updated_by_id = $request->user()?->id;

        $updatedTemplate = null;

        if ($data['action'] === 'save_as_current_template') {
            // Update the linked template if it exists
            $template = $instance->contract_template_id
                ? ContractTemplate::find($instance->contract_template_id)
                : null;

            if ($template) {
                $htmlChanged = $data['content_html'] !== $template->content_html;
                $jsonChanged = isset($data['content_json']) && json_encode($data['content_json']) !== json_encode($template->content_json);

                if ($htmlChanged || $jsonChanged) {
                    $newVersion = $template->current_version + 1;

                    $template->update([
                        'content_html'    => $data['content_html'],
                        'content_json'    => $data['content_json'] ?? $template->content_json,
                        'current_version' => $newVersion,
                        'updated_by_id'   => $request->user()?->id,
                    ]);

                    ContractTemplateVersion::create([
                        'contract_template_id' => $template->id,
                        'version'              => $newVersion,
                        'content_html'         => $data['content_html'],
                        'content_json'         => $data['content_json'] ?? null,
                        'change_note'          => $data['change_note'] ?? null,
                        'created_by_id'        => $request->user()?->id,
                    ]);

                    $instance->template_version_used = $newVersion;
                    $updatedTemplate = $template->fresh();

                    Log::info('[ContractInstanceController] contract_instance_saved_as_current_template', [
                        'template_id'     => $template->id,
                        'sign_request_id' => $signRequestId,
                        'actor_id'        => $request->user()?->id,
                    ]);
                }
            }
        } elseif ($data['action'] === 'save_as_new_template') {
            // Create a brand-new template
            $newTemplate = ContractTemplate::create([
                'name'         => $data['name'],
                'type'         => $data['type'],
                'content_html' => $data['content_html'],
                'content_json' => $data['content_json'] ?? null,
                'language'     => $data['language'] ?? 'nl',
                'location_id'  => $data['location_id'] ?? null,
                'is_global'    => empty($data['location_id']),
                'is_default'   => $data['is_default'] ?? false,
                'is_active'    => true,
                'is_archived'  => false,
                'created_by_id'  => $request->user()?->id,
                'current_version'=> 1,
            ]);

            ContractTemplateVersion::create([
                'contract_template_id' => $newTemplate->id,
                'version'              => 1,
                'content_html'         => $data['content_html'],
                'content_json'         => $data['content_json'] ?? null,
                'change_note'          => $data['change_note'] ?? 'Aangemaakt vanuit contract instantie',
                'created_by_id'        => $request->user()?->id,
            ]);

            // Link the instance to the new template
            $instance->contract_template_id  = $newTemplate->id;
            $instance->template_version_used = 1;
            $updatedTemplate = $newTemplate;

            Log::info('[ContractInstanceController] contract_instance_saved_as_new_template', [
                'new_template_id' => $newTemplate->id,
                'sign_request_id' => $signRequestId,
                'actor_id'        => $request->user()?->id,
            ]);
        } else {
            // save_instance_only
            Log::info('[ContractInstanceController] contract_instance_saved', [
                'sign_request_id' => $signRequestId,
                'actor_id'        => $request->user()?->id,
            ]);
        }

        $instance->save();

        return response()->json([
            'instance' => $instance->fresh(),
            'template' => $updatedTemplate,
        ]);
    }

    // ── POST /api/admin/contract-instances/{signRequestId}/preview ────────────

    public function preview(Request $request, int $signRequestId): JsonResponse
    {
        $data = $request->validate([
            'content_html' => 'nullable|string',
            'tags'         => 'nullable|array',
        ]);

        $instance = ContractInstance::where('sign_request_id', $signRequestId)->first();
        $html = $data['content_html'] ?? $instance?->content_html ?? '';

        if (empty($html)) {
            return response()->json(['message' => 'Geen HTML inhoud gevonden.'], 404);
        }

        // Determine yacht_id
        $yachtId = $instance?->yacht_id;
        if (! $yachtId) {
            $signRequest = SignRequest::find($signRequestId);
            if ($signRequest && ($signRequest->entity_type === 'yacht' || $signRequest->entity_type === 'App\\Models\\Yacht')) {
                $yachtId = $signRequest->entity_id;
            }
        }

        $resolvedTags = [];
        if ($yachtId) {
            $resolvedTags = $this->renderer->resolveTagsForYacht((int) $yachtId, $signRequestId);
        }

        // Manual tags override resolved tags
        $resolvedTags = array_merge($resolvedTags, $data['tags'] ?? []);

        $missingTags = $this->renderer->getMissingTags($html, array_keys($resolvedTags));
        $rendered    = $this->renderer->replaceTags($html, $resolvedTags);
        $rendered    = $this->renderer->highlightMissingTags($rendered, $missingTags);

        return response()->json([
            'html'          => $rendered,
            'missing_tags'  => $missingTags,
            'resolved_tags' => $resolvedTags,
        ]);
    }

    // ── GET /api/admin/contract-instances/{signRequestId}/tags ───────────────

    public function tags(int $signRequestId): JsonResponse
    {
        $signRequest = SignRequest::find($signRequestId);

        if (! $signRequest) {
            return response()->json(['message' => 'Sign request niet gevonden.'], 404);
        }

        // Determine yacht_id
        $yachtId = null;
        if ($signRequest->entity_type === 'yacht' || $signRequest->entity_type === 'App\\Models\\Yacht') {
            $yachtId = $signRequest->entity_id;
        }

        // Also check the instance
        if (! $yachtId) {
            $instance = ContractInstance::where('sign_request_id', $signRequestId)->first();
            $yachtId  = $instance?->yacht_id;
        }

        if (! $yachtId) {
            return response()->json([
                'tags'    => [],
                'missing' => array_keys(ContractTemplateRendererService::TAGS),
            ]);
        }

        $tags    = $this->renderer->resolveTagsForYacht((int) $yachtId, $signRequestId);
        $allKeys = array_keys(ContractTemplateRendererService::TAGS);
        $missing = array_values(array_diff($allKeys, array_keys(array_filter($tags, fn ($v) => $v !== ''))));

        return response()->json([
            'tags'    => $tags,
            'missing' => $missing,
        ]);
    }
}
