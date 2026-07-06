<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Location;
use App\Services\ContractTemplateRendererService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContractTemplateController extends Controller
{
    public function __construct(
        private readonly ContractTemplateRendererService $renderer,
    ) {}

    // ── GET /api/admin/contract-templates/types ───────────────────────────────

    public function types(): JsonResponse
    {
        // Try DB-driven ContractType when available
        try {
            if (class_exists(\App\Models\ContractType::class)) {
                $dbTypes = \App\Models\ContractType::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['slug as value', 'name as label'])
                    ->toArray();

                if (! empty($dbTypes)) {
                    return response()->json(['types' => $dbTypes]);
                }
            }
        } catch (\Throwable $e) {
            // Table may not exist yet (migration pending) — fall through to hardcoded
            Log::warning('[ContractTemplateController] contract_types table unavailable, using hardcoded fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to hardcoded constant (used before migration runs)
        $types = [];
        foreach (ContractTemplateRendererService::TEMPLATE_TYPES as $value => $label) {
            $types[] = ['value' => $value, 'label' => $label];
        }

        return response()->json(['types' => $types]);
    }

    // ── GET /api/admin/contract-templates/tags ────────────────────────────────

    public function tags(): JsonResponse
    {
        $tags = [];
        foreach (ContractTemplateRendererService::TAGS as $key => $meta) {
            $tags[] = [
                'key'      => $key,
                'label'    => $meta['label'],
                'category' => $meta['category'],
                'sample'   => $meta['sample'],
            ];
        }

        return response()->json(['tags' => $tags]);
    }

    // ── GET /api/admin/contract-templates ─────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = ContractTemplate::with(['location:id,name']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->has('location_id')) {
            $locationId = $request->input('location_id');
            if ($locationId == 0 || $locationId === 'global') {
                $query->whereNull('location_id');
            } else {
                $query->where('location_id', (int) $locationId);
            }
        }

        if ($request->has('is_global')) {
            $query->where('is_global', filter_var($request->input('is_global'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', filter_var($request->input('is_archived'), FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_archived', false);
        }

        // Return without content for list performance
        $query->select([
            'id', 'name', 'type', 'description', 'language',
            'is_global', 'is_default', 'is_active', 'is_archived',
            'location_id', 'parent_template_id',
            'created_by_id', 'updated_by_id', 'current_version',
            'created_at', 'updated_at',
        ]);

        $query->orderByDesc('is_global')->orderBy('name');

        return response()->json(['data' => $query->get()]);
    }

    // ── GET /api/admin/contract-templates/{template} ──────────────────────────

    public function show(ContractTemplate $template): JsonResponse
    {
        $template->load(['location:id,name', 'parentTemplate:id,name,type']);

        $versions = $template->versions()
            ->select(['id', 'contract_template_id', 'version', 'change_note', 'created_at', 'created_by_id'])
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'template' => $template,
            'versions' => $versions,
        ]);
    }

    // ── POST /api/admin/contract-templates ────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|max:100',
            'content_html' => 'required|string',
            'language'     => 'nullable|string|in:nl,en,de,fr',
            'location_id'  => 'nullable|integer|exists:locations,id',
            'is_global'    => 'nullable|boolean',
            'description'  => 'nullable|string|max:500',
            'content_json' => 'nullable|array',
            'change_note'  => 'nullable|string|max:500',
        ]);

        $template = ContractTemplate::create([
            'name'         => $data['name'],
            'type'         => $data['type'],
            'content_html' => $data['content_html'],
            'content_json' => $data['content_json'] ?? null,
            'language'     => $data['language'] ?? 'nl',
            'location_id'  => $data['location_id'] ?? null,
            'is_global'    => $data['is_global'] ?? false,
            'description'  => $data['description'] ?? null,
            'is_active'    => true,
            'is_archived'  => false,
            'is_default'   => false,
            'created_by_id'  => $request->user()?->id,
            'current_version'=> 1,
        ]);

        ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'content_html'         => $template->content_html,
            'content_json'         => $template->content_json,
            'change_note'          => $data['change_note'] ?? 'Initiële versie',
            'created_by_id'        => $request->user()?->id,
        ]);

        Log::info('[ContractTemplateController] contract_template_created', [
            'template_id' => $template->id,
            'type'        => $template->type,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['template' => $template], 201);
    }

    // ── PATCH /api/admin/contract-templates/{template} ────────────────────────

    public function update(Request $request, ContractTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'nullable|string|max:255',
            'type'         => 'nullable|string|max:100',
            'content_html' => 'nullable|string',
            'language'     => 'nullable|string|in:nl,en,de,fr',
            'location_id'  => 'nullable|integer|exists:locations,id',
            'is_global'    => 'nullable|boolean',
            'is_default'   => 'nullable|boolean',
            'is_active'    => 'nullable|boolean',
            'description'  => 'nullable|string|max:500',
            'content_json' => 'nullable|array',
            'change_note'  => 'nullable|string|max:500',
        ]);

        $htmlChanged = isset($data['content_html']) && $data['content_html'] !== $template->content_html;
        $jsonChanged = isset($data['content_json']) && json_encode($data['content_json']) !== json_encode($template->content_json);
        $shouldVersion = $htmlChanged || $jsonChanged;

        $fillable = array_filter([
            'name'         => $data['name']         ?? null,
            'type'         => $data['type']         ?? null,
            'content_html' => $data['content_html'] ?? null,
            'content_json' => $data['content_json'] ?? null,
            'language'     => $data['language']     ?? null,
            'location_id'  => array_key_exists('location_id', $data) ? $data['location_id'] : null,
            'is_global'    => $data['is_global']    ?? null,
            'is_default'   => $data['is_default']   ?? null,
            'is_active'    => $data['is_active']    ?? null,
            'description'  => $data['description']  ?? null,
            'updated_by_id'=> $request->user()?->id,
        ], fn ($v) => $v !== null);

        if ($shouldVersion) {
            $fillable['current_version'] = $template->current_version + 1;
        }

        $template->update($fillable);

        if ($shouldVersion) {
            ContractTemplateVersion::create([
                'contract_template_id' => $template->id,
                'version'              => $template->current_version,
                'content_html'         => $template->content_html,
                'content_json'         => $template->content_json,
                'change_note'          => $data['change_note'] ?? null,
                'created_by_id'        => $request->user()?->id,
            ]);
        }

        Log::info('[ContractTemplateController] contract_template_updated', [
            'template_id' => $template->id,
            'new_version' => $shouldVersion ? $template->current_version : null,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['template' => $template->fresh()]);
    }

    // ── DELETE /api/admin/contract-templates/{template} ───────────────────────

    public function destroy(Request $request, ContractTemplate $template): JsonResponse
    {
        $template->update([
            'is_archived' => true,
            'is_active'   => false,
        ]);

        Log::info('[ContractTemplateController] contract_template_deleted', [
            'template_id' => $template->id,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['message' => 'Sjabloon gearchiveerd.']);
    }

    // ── POST /api/admin/contract-templates/{template}/duplicate ──────────────

    public function duplicate(Request $request, ContractTemplate $template): JsonResponse
    {
        $copy = ContractTemplate::create([
            'name'             => 'Kopie van ' . $template->name,
            'type'             => $template->type,
            'content_html'     => $template->content_html,
            'content_json'     => $template->content_json,
            'language'         => $template->language,
            'location_id'      => $template->location_id,
            'parent_template_id'=> $template->id,
            'is_global'        => false,
            'is_default'       => false,
            'is_active'        => true,
            'is_archived'      => false,
            'description'      => $template->description,
            'created_by_id'    => $request->user()?->id,
            'current_version'  => 1,
        ]);

        ContractTemplateVersion::create([
            'contract_template_id' => $copy->id,
            'version'              => 1,
            'content_html'         => $copy->content_html,
            'content_json'         => $copy->content_json,
            'change_note'          => "Gekopieerd van sjabloon #{$template->id}",
            'created_by_id'        => $request->user()?->id,
        ]);

        Log::info('[ContractTemplateController] contract_template_duplicated', [
            'source_id' => $template->id,
            'copy_id'   => $copy->id,
            'actor_id'  => $request->user()?->id,
        ]);

        return response()->json(['template' => $copy], 201);
    }

    // ── POST /api/admin/contract-templates/{template}/assign-to-location ─────

    public function assignToLocation(Request $request, ContractTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        $locationId = (int) $data['location_id'];

        // Check if location already has a template of this type
        $exists = ContractTemplate::where('location_id', $locationId)
            ->where('type', $template->type)
            ->where('is_archived', false)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Locatie heeft al een sjabloon van dit type',
            ], 409);
        }

        $locationName = Location::find($locationId)?->name ?? 'Locatie';

        $copy = ContractTemplate::create([
            'name'              => $locationName . ' — ' . (ContractTemplateRendererService::TEMPLATE_TYPES[$template->type] ?? $template->type),
            'type'              => $template->type,
            'content_html'      => $template->content_html,
            'content_json'      => $template->content_json,
            'language'          => $template->language,
            'location_id'       => $locationId,
            'parent_template_id'=> $template->id,
            'is_global'         => false,
            'is_active'         => true,
            'is_archived'       => false,
            'description'       => $template->description,
            'created_by_id'     => $request->user()?->id,
            'current_version'   => 1,
        ]);

        // Set as default if there's no existing default for this location+type
        $hasDefault = ContractTemplate::where('location_id', $locationId)
            ->where('type', $template->type)
            ->where('is_default', true)
            ->exists();

        if (! $hasDefault) {
            $copy->update(['is_default' => true]);
        }

        ContractTemplateVersion::create([
            'contract_template_id' => $copy->id,
            'version'              => 1,
            'content_html'         => $copy->content_html,
            'content_json'         => $copy->content_json,
            'change_note'          => "Toegewezen vanuit globaal sjabloon #{$template->id}",
            'created_by_id'        => $request->user()?->id,
        ]);

        Log::info('[ContractTemplateController] contract_template_assigned_to_location', [
            'source_id'   => $template->id,
            'copy_id'     => $copy->id,
            'location_id' => $locationId,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['template' => $copy->fresh()], 201);
    }

    // ── POST /api/admin/contract-templates/{template}/set-default ────────────

    public function setDefault(Request $request, ContractTemplate $template): JsonResponse
    {
        // Unset default for all other templates with same location_id + type
        ContractTemplate::where('type', $template->type)
            ->where(function ($q) use ($template) {
                if ($template->location_id) {
                    $q->where('location_id', $template->location_id);
                } else {
                    $q->whereNull('location_id');
                }
            })
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->update(['is_default' => true]);

        Log::info('[ContractTemplateController] contract_template_default_set', [
            'template_id' => $template->id,
            'type'        => $template->type,
            'location_id' => $template->location_id,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['template' => $template->fresh()]);
    }

    // ── GET /api/admin/contract-templates/{template}/versions ─────────────────

    public function versions(ContractTemplate $template): JsonResponse
    {
        $versions = $template->versions()
            ->orderByDesc('version')
            ->get();

        return response()->json(['versions' => $versions]);
    }

    // ── POST /api/admin/contract-templates/{template}/restore-version ─────────

    public function restoreVersion(Request $request, ContractTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'version' => 'required|integer|min:1',
        ]);

        $version = ContractTemplateVersion::where('contract_template_id', $template->id)
            ->where('version', $data['version'])
            ->firstOrFail();

        $newVersionNumber = $template->current_version + 1;

        $template->update([
            'content_html'    => $version->content_html,
            'content_json'    => $version->content_json,
            'current_version' => $newVersionNumber,
            'updated_by_id'   => $request->user()?->id,
        ]);

        ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => $newVersionNumber,
            'content_html'         => $version->content_html,
            'content_json'         => $version->content_json,
            'change_note'          => "Hersteld van versie {$data['version']}",
            'created_by_id'        => $request->user()?->id,
        ]);

        Log::info('[ContractTemplateController] contract_template_version_restored', [
            'template_id'   => $template->id,
            'restored_from' => $data['version'],
            'new_version'   => $newVersionNumber,
            'actor_id'      => $request->user()?->id,
        ]);

        return response()->json(['template' => $template->fresh()]);
    }

    // ── POST /api/admin/contract-templates/{template}/preview ─────────────────

    public function preview(Request $request, ContractTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'use_sample_tags' => 'nullable|boolean',
            'tags'            => 'nullable|array',
            'yacht_id'        => 'nullable|integer',
            'content_html'    => 'nullable|string',   // allow editor to preview unsaved content
        ]);

        $useSample = filter_var($data['use_sample_tags'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $manualTags = $data['tags'] ?? [];
        $resolvedTags = [];

        if ($useSample) {
            $resolvedTags = $this->renderer->getSampleTags();
        }

        if (! empty($data['yacht_id'])) {
            $yachtTags = $this->renderer->resolveTagsForYacht((int) $data['yacht_id']);
            $resolvedTags = array_merge($resolvedTags, $yachtTags);
        }

        // Manual tags take highest priority
        $resolvedTags = array_merge($resolvedTags, $manualTags);

        // Use content_html from request (unsaved editor content) if provided, else use saved version
        $html = $data['content_html'] ?? $template->content_html;
        $missingTags = $this->renderer->getMissingTags($html, array_keys($resolvedTags));
        $html = $this->renderer->replaceTags($html, $resolvedTags);
        $html = $this->renderer->highlightMissingTags($html, $missingTags);

        return response()->json([
            'html'          => $html,
            'missing_tags'  => $missingTags,
            'resolved_tags' => $resolvedTags,
        ]);
    }

    // ── GET /api/admin/contract-templates/{template}/pdf ─────────────────────

    public function pdf(Request $request, ContractTemplate $template): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            // GET query strings send "true"/"false" as strings — skip boolean rule,
            // use filter_var below instead
            'yacht_id' => 'nullable|integer',
        ]);

        // filter_var handles "true", "1", true, 1, "false", "0", false, 0
        $useSample = filter_var($request->input('use_sample_tags', 'true'), FILTER_VALIDATE_BOOLEAN);
        $resolvedTags = $useSample ? $this->renderer->getSampleTags() : [];

        $yachtId = $request->integer('yacht_id');
        if ($yachtId > 0) {
            $resolvedTags = array_merge($resolvedTags, $this->renderer->resolveTagsForYacht($yachtId));
        }

        $contentHtml = $template->content_html ?? '';

        // Guard: empty template body would produce a blank PDF
        if (trim(strip_tags($contentHtml)) === '') {
            return response()->json(['message' => 'This template has no content. Add text in the editor first.'], 422);
        }

        $html = $this->renderer->replaceTags($contentHtml, $resolvedTags);

        // Wrap in a print-ready HTML document
        $fullHtml = $this->buildPrintHtml($html, $template->name);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($fullHtml)
                // HTML5 parser handles TipTap output better than the legacy parser
                ->setOption('isHtml5ParserEnabled', true)
                // DejaVu Sans is bundled with dompdf — Arial is not available on most VPS servers
                ->setOption('defaultFont', 'DejaVu Sans')
                ->setOption('isRemoteEnabled', false);
            $pdf->setPaper('A4', 'portrait');
            $filename = \Illuminate\Support\Str::slug($template->name) . '.pdf';
            return $pdf->download($filename);
        }

        // Dompdf not installed — return rendered HTML for browser printing
        return response($fullHtml, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function buildPrintHtml(string $body, string $title): string
    {
        // Use ASCII-safe title for PDF metadata (avoids DomPDF mojibake in PDF title field)
        $safeTitle = mb_convert_encoding($title, 'UTF-8', 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>{$safeTitle}</title>
<style>
  @page { margin: 25mm 20mm; size: A4; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.7; }
  h1 { font-family: DejaVu Sans, sans-serif; font-size: 16pt; font-weight: bold; color: #1a1a1a; margin-bottom: 8pt; }
  h2 { font-family: DejaVu Sans, sans-serif; font-size: 13pt; font-weight: bold; color: #1a1a1a; margin-top: 16pt; margin-bottom: 6pt; }
  h3 { font-family: DejaVu Sans, sans-serif; font-size: 11pt; font-weight: bold; color: #1a1a1a; margin-top: 12pt; margin-bottom: 4pt; }
  p  { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; margin: 6pt 0; }
  hr { border: none; border-top: 1px solid #ccc; margin: 16pt 0; page-break-after: auto; }
  strong { font-weight: bold; }
  em { font-style: italic; }
  u  { text-decoration: underline; }
  ul, ol { padding-left: 18pt; margin: 6pt 0; }
  li { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; margin: 3pt 0; }
  .missing-tag { background: #fde8e8; color: #c81020; padding: 0 2px; }
  span { color: #1a1a1a; }
</style>
</head>
<body>{$body}</body>
</html>
HTML;
    }

    // ── GET /api/admin/contract-templates/default-for-location ───────────────

    public function defaultForLocation(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'nullable|integer',
            'type'        => 'nullable|string',
        ]);

        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;
        $type = $request->input('type', 'purchase_contract');

        // Try location-specific default first
        $template = null;

        if ($locationId) {
            $template = ContractTemplate::where('location_id', $locationId)
                ->where('type', $type)
                ->where('is_default', true)
                ->where('is_archived', false)
                ->first();

            // Fallback: any active template for this location+type
            if (! $template) {
                $template = ContractTemplate::where('location_id', $locationId)
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->where('is_archived', false)
                    ->first();
            }
        }

        // Fallback: global default
        if (! $template) {
            $template = ContractTemplate::whereNull('location_id')
                ->where('type', $type)
                ->where('is_default', true)
                ->where('is_archived', false)
                ->first();
        }

        // Fallback: any global template
        if (! $template) {
            $template = ContractTemplate::whereNull('location_id')
                ->where('type', $type)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->first();
        }

        return response()->json(['template' => $template]);
    }
}
