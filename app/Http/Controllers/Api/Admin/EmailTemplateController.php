<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Services\EmailTemplateAutoCreateService;
use App\Services\EmailTemplateRendererService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateRendererService $renderer,
    ) {}

    // ── GET /api/admin/email-templates ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = EmailTemplate::with(['location:id,name'])
            ->orderByDesc('updated_at');

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
            $query->where('is_global', (bool) $request->input('is_global'));
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', (bool) $request->input('is_archived'));
        } else {
            $query->where('is_archived', false);
        }

        // Return without blocks for listing performance
        $query->select([
            'id', 'type', 'name', 'description', 'is_global', 'location_id',
            'parent_template_id', 'language_default', 'subject', 'preheader',
            'sender_name_override', 'sender_email_override', 'reply_to_override',
            'primary_color_override', 'is_active', 'is_archived',
            'created_by_id', 'current_version', 'created_at', 'updated_at',
        ]);

        $perPage = (int) $request->input('per_page', 0);

        if ($perPage > 0) {
            $templates = $query->paginate($perPage);
            return response()->json([
                'data'      => $templates->items(),
                'total'     => $templates->total(),
                'page'      => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
            ]);
        }

        return response()->json(['data' => $query->get()]);
    }

    // ── GET /api/admin/email-templates/{template} ─────────────────────────────

    public function show(EmailTemplate $template): JsonResponse
    {
        $template->load(['location:id,name', 'parentTemplate:id,name,type']);

        $versions = $template->versions()
            ->select(['id', 'email_template_id', 'version', 'created_at', 'created_by_id', 'change_note'])
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'template' => $template,
            'versions' => $versions,
        ]);
    }

    // ── POST /api/admin/email-templates ───────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'                  => 'required|string|max:100',
            'name'                  => 'required|string|max:255',
            'subject'               => 'required|array',
            'subject.nl'            => 'required|string|max:500',
            'subject.en'            => 'nullable|string|max:500',
            'subject.de'            => 'nullable|string|max:500',
            'subject.fr'            => 'nullable|string|max:500',
            'preheader'             => 'nullable|string|max:500',
            'blocks'                => 'required|array',
            'description'           => 'nullable|string|max:500',
            'location_id'           => 'nullable|integer|exists:locations,id',
            'is_global'             => 'nullable|boolean',
            'language_default'      => 'nullable|string|in:nl,en,de,fr',
            'sender_name_override'  => 'nullable|string|max:255',
            'sender_email_override' => 'nullable|email|max:255',
            'reply_to_override'     => 'nullable|email|max:255',
            'primary_color_override'=> 'nullable|string|max:20',
            'change_note'           => 'nullable|string|max:500',
        ]);

        $template = EmailTemplate::create([
            'type'                  => $data['type'],
            'name'                  => $data['name'],
            'description'           => $data['description'] ?? null,
            'is_global'             => $data['is_global'] ?? false,
            'location_id'           => $data['location_id'] ?? null,
            'language_default'      => $data['language_default'] ?? 'nl',
            'subject'               => $data['subject'],
            'preheader'             => $data['preheader'] ?? null,
            'blocks'                => $data['blocks'],
            'sender_name_override'  => $data['sender_name_override'] ?? null,
            'sender_email_override' => $data['sender_email_override'] ?? null,
            'reply_to_override'     => $data['reply_to_override'] ?? null,
            'primary_color_override'=> $data['primary_color_override'] ?? null,
            'is_active'             => true,
            'is_archived'           => false,
            'created_by_id'         => $request->user()?->id,
            'current_version'       => 1,
        ]);

        EmailTemplateVersion::create([
            'email_template_id' => $template->id,
            'version'           => 1,
            'subject'           => $data['subject'],
            'blocks'            => $data['blocks'],
            'change_note'       => $data['change_note'] ?? 'Initiële versie',
            'created_by_id'     => $request->user()?->id,
        ]);

        Log::info('[EmailTemplateController] Template created', [
            'template_id' => $template->id,
            'type'        => $template->type,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['template' => $template], 201);
    }

    // ── PATCH /api/admin/email-templates/{template} ───────────────────────────

    public function update(Request $request, EmailTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'nullable|string|max:255',
            'description'           => 'nullable|string|max:500',
            'subject'               => 'nullable|array',
            'subject.nl'            => 'nullable|string|max:500',
            'subject.en'            => 'nullable|string|max:500',
            'subject.de'            => 'nullable|string|max:500',
            'subject.fr'            => 'nullable|string|max:500',
            'preheader'             => 'nullable|string|max:500',
            'blocks'                => 'nullable|array',
            'language_default'      => 'nullable|string|in:nl,en,de,fr',
            'sender_name_override'  => 'nullable|string|max:255',
            'sender_email_override' => 'nullable|email|max:255',
            'reply_to_override'     => 'nullable|email|max:255',
            'primary_color_override'=> 'nullable|string|max:20',
            'is_active'             => 'nullable|boolean',
            'change_note'           => 'nullable|string|max:500',
        ]);

        $blocksChanged  = isset($data['blocks'])  && json_encode($data['blocks'])  !== json_encode($template->blocks);
        $subjectChanged = isset($data['subject']) && json_encode($data['subject']) !== json_encode($template->subject);
        $shouldVersion  = $blocksChanged || $subjectChanged;

        $fillable = array_filter([
            'name'                  => $data['name']                   ?? null,
            'description'           => $data['description']            ?? null,
            'subject'               => $data['subject']                ?? null,
            'preheader'             => $data['preheader']              ?? null,
            'blocks'                => $data['blocks']                 ?? null,
            'language_default'      => $data['language_default']       ?? null,
            'sender_name_override'  => $data['sender_name_override']   ?? null,
            'sender_email_override' => $data['sender_email_override']  ?? null,
            'reply_to_override'     => $data['reply_to_override']      ?? null,
            'primary_color_override'=> $data['primary_color_override'] ?? null,
            'is_active'             => $data['is_active']              ?? null,
        ], fn ($v) => $v !== null);

        if ($shouldVersion) {
            $fillable['current_version'] = $template->current_version + 1;
        }

        $template->update($fillable);

        if ($shouldVersion) {
            EmailTemplateVersion::create([
                'email_template_id' => $template->id,
                'version'           => $template->current_version,
                'subject'           => $template->subject,
                'blocks'            => $template->blocks,
                'change_note'       => $data['change_note'] ?? null,
                'created_by_id'     => $request->user()?->id,
            ]);
        }

        Log::info('[EmailTemplateController] Template updated', [
            'template_id'    => $template->id,
            'new_version'    => $shouldVersion ? $template->current_version : null,
            'actor_id'       => $request->user()?->id,
        ]);

        return response()->json(['template' => $template->fresh()]);
    }

    // ── DELETE /api/admin/email-templates/{template} ──────────────────────────
    // Soft-archive: sets is_archived=true, is_active=false

    public function destroy(Request $request, EmailTemplate $template): JsonResponse
    {
        $template->update([
            'is_archived' => true,
            'is_active'   => false,
        ]);

        Log::info('[EmailTemplateController] Template archived', [
            'template_id' => $template->id,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['message' => 'Sjabloon gearchiveerd.']);
    }

    // ── POST /api/admin/email-templates/{template}/duplicate ─────────────────

    public function duplicate(Request $request, EmailTemplate $template): JsonResponse
    {
        $copy = EmailTemplate::create([
            'type'                  => $template->type,
            'name'                  => 'Kopie van ' . $template->name,
            'description'           => $template->description,
            'is_global'             => false,
            'location_id'           => $template->location_id,
            'parent_template_id'    => $template->id,
            'language_default'      => $template->language_default,
            'subject'               => $template->subject,
            'preheader'             => $template->preheader,
            'blocks'                => $template->blocks,
            'sender_name_override'  => $template->sender_name_override,
            'sender_email_override' => $template->sender_email_override,
            'reply_to_override'     => $template->reply_to_override,
            'primary_color_override'=> $template->primary_color_override,
            'is_active'             => true,
            'is_archived'           => false,
            'created_by_id'         => $request->user()?->id,
            'current_version'       => 1,
        ]);

        EmailTemplateVersion::create([
            'email_template_id' => $copy->id,
            'version'           => 1,
            'subject'           => $copy->subject,
            'blocks'            => $copy->blocks,
            'change_note'       => "Gekopieerd van sjabloon #{$template->id}",
            'created_by_id'     => $request->user()?->id,
        ]);

        Log::info('[EmailTemplateController] Template duplicated', [
            'source_id' => $template->id,
            'copy_id'   => $copy->id,
            'actor_id'  => $request->user()?->id,
        ]);

        return response()->json(['template' => $copy], 201);
    }

    // ── POST /api/admin/email-templates/{template}/assign-to-location ─────────

    public function assignToLocation(Request $request, EmailTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        $locationId = (int) $data['location_id'];

        // Check if already assigned
        $exists = EmailTemplate::where('location_id', $locationId)
            ->where('type', $template->type)
            ->where('is_archived', false)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Deze vestiging heeft al een sjabloon van dit type.',
            ], 422);
        }

        $copy = EmailTemplate::create([
            'type'                  => $template->type,
            'name'                  => \App\Models\Location::find($locationId)?->name . ' — ' . (EmailTemplateAutoCreateService::READABLE_NAMES[$template->type] ?? $template->type),
            'description'           => $template->description,
            'is_global'             => false,
            'location_id'           => $locationId,
            'parent_template_id'    => $template->id,
            'language_default'      => $template->language_default,
            'subject'               => $template->subject,
            'preheader'             => $template->preheader,
            'blocks'                => $template->blocks,
            'sender_name_override'  => $template->sender_name_override,
            'sender_email_override' => $template->sender_email_override,
            'reply_to_override'     => $template->reply_to_override,
            'primary_color_override'=> $template->primary_color_override,
            'is_active'             => true,
            'is_archived'           => false,
            'created_by_id'         => $request->user()?->id,
            'current_version'       => 1,
        ]);

        EmailTemplateVersion::create([
            'email_template_id' => $copy->id,
            'version'           => 1,
            'subject'           => $copy->subject,
            'blocks'            => $copy->blocks,
            'change_note'       => "Toegewezen vanuit globaal sjabloon #{$template->id}",
            'created_by_id'     => $request->user()?->id,
        ]);

        Log::info('[EmailTemplateController] Template assigned to location', [
            'source_id'   => $template->id,
            'copy_id'     => $copy->id,
            'location_id' => $locationId,
            'actor_id'    => $request->user()?->id,
        ]);

        return response()->json(['template' => $copy], 201);
    }

    // ── GET /api/admin/email-templates/{template}/versions ────────────────────

    public function versions(EmailTemplate $template): JsonResponse
    {
        $versions = $template->versions()
            ->orderByDesc('version')
            ->get();

        return response()->json(['versions' => $versions]);
    }

    // ── POST /api/admin/email-templates/{template}/restore-version ───────────

    public function restoreVersion(Request $request, EmailTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'version' => 'required|integer|min:1',
        ]);

        $version = EmailTemplateVersion::where('email_template_id', $template->id)
            ->where('version', $data['version'])
            ->firstOrFail();

        $newVersionNumber = $template->current_version + 1;

        $template->update([
            'subject'         => $version->subject,
            'blocks'          => $version->blocks,
            'current_version' => $newVersionNumber,
        ]);

        EmailTemplateVersion::create([
            'email_template_id' => $template->id,
            'version'           => $newVersionNumber,
            'subject'           => $version->subject,
            'blocks'            => $version->blocks,
            'change_note'       => "Hersteld van versie {$data['version']}",
            'created_by_id'     => $request->user()?->id,
        ]);

        Log::info('[EmailTemplateController] Template version restored', [
            'template_id'     => $template->id,
            'restored_from'   => $data['version'],
            'new_version'     => $newVersionNumber,
            'actor_id'        => $request->user()?->id,
        ]);

        return response()->json(['template' => $template->fresh()]);
    }

    // ── POST /api/admin/email-templates/{template}/preview ────────────────────
    // Uses blocks from the request body so unsaved changes are reflected live.

    public function preview(Request $request, EmailTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'lang'    => 'nullable|string|in:nl,en,de,fr',
            'tags'    => 'nullable|array',
            'blocks'  => 'nullable|array',
            'subject' => 'nullable|array',
        ]);

        $lang     = $data['lang'] ?? $template->language_default ?? 'nl';
        $tags     = $data['tags'] ?? [];
        $branding = $template->primary_color_override
            ? ['primary_color' => $template->primary_color_override]
            : null;

        // Prefer live blocks from the request (unsaved editor state); fall back to saved
        $blocks  = $data['blocks']  ?? $template->blocks;
        $subject = $data['subject'] ?? $template->subject;

        if (empty($tags)) {
            $tags = $this->renderer->sampleData($template->type);
        }

        $html           = $this->renderer->render($blocks, $lang, $tags, $branding);
        $subjectRendered = $this->renderer->replaceTags($subject[$lang] ?? $subject['nl'] ?? '', $tags);

        return response()->json([
            'html'    => $html,
            'subject' => $subjectRendered,
        ]);
    }

    // ── POST /api/admin/email-templates/{template}/test-send ─────────────────

    public function testSend(Request $request, EmailTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'email'   => 'required|email|max:255',
            'lang'    => 'nullable|string|in:nl,en,de,fr',
            'tags'    => 'nullable|array',
            'blocks'  => 'nullable|array',
            'subject' => 'nullable|array',
        ]);

        $lang     = $data['lang'] ?? $template->language_default ?? 'nl';
        $branding = $template->primary_color_override
            ? ['primary_color' => $template->primary_color_override]
            : null;

        $blocks  = $data['blocks']  ?? $template->blocks;
        $subject = $data['subject'] ?? $template->subject;
        $tags    = !empty($data['tags']) ? $data['tags'] : $this->renderer->sampleData($template->type);

        $html             = $this->renderer->render($blocks, $lang, $tags, $branding);
        $subjectRendered  = $this->renderer->replaceTags($subject[$lang] ?? $subject['nl'] ?? '', $tags);

        try {
            Mail::html($html, function ($message) use ($data, $subjectRendered, $template) {
                $message->to($data['email'])
                        ->subject('[TEST] ' . $subjectRendered);

                if ($template->sender_name_override || $template->sender_email_override) {
                    $message->from(
                        $template->sender_email_override ?? config('mail.from.address'),
                        $template->sender_name_override ?? config('mail.from.name'),
                    );
                }

                if ($template->reply_to_override) {
                    $message->replyTo($template->reply_to_override);
                }
            });

            Log::info('[EmailTemplateController] Test email sent', [
                'template_id' => $template->id,
                'to'          => $data['email'],
                'actor_id'    => $request->user()?->id,
            ]);

            return response()->json(['message' => 'Test e-mail verzonden.']);
        } catch (\Throwable $e) {
            Log::error('[EmailTemplateController] Test email failed', [
                'template_id' => $template->id,
                'to'          => $data['email'],
                'error'       => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Verzenden mislukt: ' . $e->getMessage()], 500);
        }
    }

    // ── GET /api/admin/email-templates/types ─────────────────────────────────

    public function types(): JsonResponse
    {
        $types = [];
        foreach (EmailTemplateAutoCreateService::TEMPLATE_TYPES as $type) {
            $types[] = [
                'value' => $type,
                'label' => EmailTemplateAutoCreateService::READABLE_NAMES[$type] ?? $type,
            ];
        }

        return response()->json($types);
    }

    // ── GET /api/admin/email-templates/tags ──────────────────────────────────
    // Returns tags grouped by category for the editor tag-insert panel.

    public function tags(): JsonResponse
    {
        // Build reverse lookup: tag → category
        $tagToCategory = [];
        foreach (EmailTemplateRendererService::TAG_CATEGORIES as $category => $categoryTags) {
            foreach ($categoryTags as $tag) {
                $tagToCategory[$tag] = $category;
            }
        }

        $flat = [];
        foreach (EmailTemplateRendererService::TAGS as $tag) {
            $flat[] = [
                'key'         => $tag,
                'description' => EmailTemplateRendererService::TAG_DESCRIPTIONS[$tag] ?? $tag,
                'category'    => $tagToCategory[$tag] ?? 'Other',
            ];
        }

        return response()->json([
            'tags'       => $flat,
            'categories' => array_keys(EmailTemplateRendererService::TAG_CATEGORIES),
        ]);
    }

    // ── GET /api/admin/email-templates/{template}/sample-data ─────────────────

    public function sampleData(EmailTemplate $template): JsonResponse
    {
        return response()->json([
            'type' => $template->type,
            'data' => $this->renderer->sampleData($template->type),
        ]);
    }

    // ── GET /api/admin/email-templates/sample-data/{type} ────────────────────

    public function sampleDataByType(string $type): JsonResponse
    {
        return response()->json([
            'type' => $type,
            'data' => $this->renderer->sampleData($type),
        ]);
    }

    // ── POST /api/admin/email-templates/upload-media ─────────────────────────

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|image|max:4096',
        ]);

        $path = $request->file('file')->store('email-template-media', 'public');
        $url  = asset('storage/' . $path);

        return response()->json(['url' => $url]);
    }
}
