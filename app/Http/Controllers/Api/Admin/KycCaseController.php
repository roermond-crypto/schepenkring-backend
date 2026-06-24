<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Kyc\CreateKycCaseAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deal;
use App\Models\KycAnswer;
use App\Models\KycCase;
use App\Models\KycDocument;
use App\Models\KycQuestionTemplate;
use App\Services\KycRiskEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KycCaseController extends Controller
{
    public function __construct(private KycRiskEngine $risk) {}

    // ── GET /admin/kyc-cases ─────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = KycCase::with(['buyer:id,name,email', 'seller:id,name', 'yacht:id,boat_name', 'location:id,name'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($level = $request->input('risk_level')) {
            $query->where('risk_level', $level);
        }
        if ($locationId = $request->integer('location_id')) {
            $query->where('location_id', $locationId);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                  ->orWhere('buyer_name', 'like', "%{$search}%")
                  ->orWhere('buyer_email', 'like', "%{$search}%")
                  ->orWhere('seller_name', 'like', "%{$search}%")
                  ->orWhere('boat_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 30);
        $items   = $query->paginate($perPage);

        // Dashboard stats
        $stats = [
            'open'              => KycCase::whereNotIn('status', ['approved', 'rejected', 'reported'])->count(),
            'waiting_documents' => KycCase::where('status', 'waiting_documents')->count(),
            'high_risk'         => KycCase::whereIn('risk_level', ['high', 'critical'])->count(),
            'under_review'      => KycCase::where('status', 'under_review')->count(),
            'approved_month'    => KycCase::where('status', 'approved')->whereMonth('approved_at', now()->month)->count(),
        ];

        return response()->json([
            'data'      => $items->map(fn ($c) => $this->serialize($c)),
            'total'     => $items->total(),
            'page'      => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'stats'     => $stats,
        ]);
    }

    // ── GET /admin/kyc-cases/{kycCase} ───────────────────────

    public function show(KycCase $kycCase): JsonResponse
    {
        $kycCase->load([
            'buyer:id,name,email,phone',
            'seller:id,name,email,phone',
            'yacht:id,boat_name,price,boat_type',
            'location:id,name,email,phone',
            'broker:id,name',
            'approvedBy:id,name',
            'answers.question',
            'answers.answeredBy:id,name',
            'documents.uploadedBy:id,name',
            'documents.verifiedBy:id,name',
            'timeline.actor:id,name',
        ]);

        $sections  = KycQuestionTemplate::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section');

        return response()->json([
            'kyc_case'  => $this->serializeFull($kycCase),
            'sections'  => $this->serializeSections($sections, $kycCase),
            'progress'  => $kycCase->sectionProgress(),
            'missing_documents' => $kycCase->missingDocumentTypes(),
        ]);
    }

    // ── POST /admin/kyc-cases ─────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deal_id'        => 'nullable|integer|exists:deals,id',
            'offer_id'       => 'nullable|integer|exists:offers,id',
            'buyer_id'       => 'nullable|integer|exists:users,id',
            'seller_id'      => 'nullable|integer|exists:users,id',
            'yacht_id'       => 'nullable|integer|exists:yachts,id',
            'location_id'    => 'nullable|integer|exists:locations,id',
            'deal_value'     => 'nullable|numeric',
            'payment_method' => 'nullable|string',
        ]);

        $action  = app(CreateKycCaseAction::class);
        $kycCase = $action->manual($data);

        $this->audit('kyc_created', $kycCase, $request);

        return response()->json(['kyc_case' => $this->serialize($kycCase)], 201);
    }

    // ── POST /admin/kyc-cases/{kycCase}/answers ───────────────

    public function saveAnswer(Request $request, KycCase $kycCase): JsonResponse
    {
        $data = $request->validate([
            'question_template_id' => 'required|integer|exists:kyc_question_templates,id',
            'answer'               => 'required|string|max:5000',
            'note'                 => 'nullable|string|max:2000',
        ]);

        $question = KycQuestionTemplate::findOrFail($data['question_template_id']);
        $points   = $this->risk->pointsForAnswer($question, $data['answer']);

        $answer = KycAnswer::updateOrCreate(
            ['kyc_case_id' => $kycCase->id, 'question_template_id' => $question->id],
            [
                'answer'             => $data['answer'],
                'note'               => $data['note'] ?? null,
                'risk_points_awarded' => $points,
                'answered_by'        => $request->user()->id,
                'answered_at'        => now(),
            ]
        );

        // Recalculate risk
        $riskResult = $this->risk->recalculate($kycCase);
        $blocking   = $this->risk->checkBlocking($kycCase);

        // Timeline
        $kycCase->addTimeline(
            'question_answered',
            "Vraag beantwoord: {$question->section} — " . substr($question->question, 0, 80),
            $request->user(),
            ['question_id' => $question->id, 'answer' => $data['answer'], 'points' => $points],
            $request->ip(),
            $request->userAgent()
        );

        // Advance status from draft
        if ($kycCase->status === 'draft') {
            $kycCase->update(['status' => 'in_progress']);
        }

        // Audit
        $this->audit('kyc_answer_saved', $kycCase, $request, ['question_id' => $question->id]);

        return response()->json([
            'answer'   => $answer,
            'risk'     => $riskResult,
            'blocking' => $blocking,
        ]);
    }

    // ── POST /admin/kyc-cases/{kycCase}/documents ─────────────

    public function uploadDocument(Request $request, KycCase $kycCase): JsonResponse
    {
        $request->validate([
            'file'          => 'required|file|max:30720',
            'party'         => 'required|in:buyer,seller,boat',
            'document_type' => 'required|string|max:60',
        ]);

        $file  = $request->file('file');
        $path  = $file->store("kyc/{$kycCase->id}/{$request->party}", 'private');

        $doc = KycDocument::create([
            'kyc_case_id'   => $kycCase->id,
            'party'         => $request->input('party'),
            'document_type' => $request->input('document_type'),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'disk'          => 'private',
            'uploaded_by'   => $request->user()->id,
        ]);

        $kycCase->addTimeline(
            'document_uploaded',
            "Document geüpload: {$request->document_type} ({$request->party})",
            $request->user(),
            ['document_id' => $doc->id, 'party' => $request->party],
            $request->ip(),
            $request->userAgent()
        );

        if ($kycCase->status === 'waiting_documents') {
            $kycCase->update(['status' => 'under_review']);
        }

        $this->audit('kyc_document_uploaded', $kycCase, $request, ['doc_id' => $doc->id]);

        return response()->json(['document' => $doc]);
    }

    // ── POST /admin/kyc-cases/{kycCase}/verify-document ───────

    public function verifyDocument(Request $request, KycCase $kycCase): JsonResponse
    {
        $request->validate(['document_id' => 'required|integer|exists:kyc_documents,id']);

        $doc = KycDocument::where('id', $request->document_id)
            ->where('kyc_case_id', $kycCase->id)
            ->firstOrFail();

        $doc->update([
            'verified'    => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $kycCase->addTimeline(
            'document_verified',
            "Document geverifieerd: {$doc->document_type}",
            $request->user(),
            null,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json(['document' => $doc->fresh()]);
    }

    // ── PATCH /admin/kyc-cases/{kycCase}/status ───────────────

    public function updateStatus(Request $request, KycCase $kycCase): JsonResponse
    {
        $data = $request->validate([
            'status'           => 'required|in:draft,in_progress,waiting_documents,under_review,approved,rejected,high_risk,reported',
            'rejection_reason' => 'nullable|string|max:1000',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $oldStatus = $kycCase->status;

        $update = ['status' => $data['status']];
        if ($data['status'] === 'approved') {
            $update['approved_by'] = $request->user()->id;
            $update['approved_at'] = now();
            $update['blocking']    = false;
        }
        if (isset($data['rejection_reason'])) {
            $update['rejection_reason'] = $data['rejection_reason'];
        }
        if (isset($data['notes'])) {
            $update['notes'] = $data['notes'];
        }

        $kycCase->update($update);

        $kycCase->addTimeline(
            'status_changed',
            "Status gewijzigd van {$oldStatus} naar {$data['status']}",
            $request->user(),
            ['old_status' => $oldStatus, 'new_status' => $data['status']],
            $request->ip(),
            $request->userAgent()
        );

        $this->audit('kyc_status_changed', $kycCase, $request, [
            'old_status' => $oldStatus,
            'new_status' => $data['status'],
        ]);

        return response()->json(['kyc_case' => $this->serialize($kycCase->fresh())]);
    }

    // ── GET /admin/kyc-cases/{kycCase}/questions ──────────────

    public function questions(KycCase $kycCase): JsonResponse
    {
        $templates = KycQuestionTemplate::where('active', true)
            ->orderBy('sort_order')
            ->get();

        $answers = KycAnswer::where('kyc_case_id', $kycCase->id)
            ->pluck('answer', 'question_template_id');

        $notes = KycAnswer::where('kyc_case_id', $kycCase->id)
            ->pluck('note', 'question_template_id');

        return response()->json([
            'questions' => $templates->map(fn ($t) => [
                'id'                        => $t->id,
                'section'                   => $t->section,
                'question'                  => $t->question,
                'action'                    => $t->action,
                'field_type'                => $t->field_type,
                'options'                   => $t->options,
                'risk_points'               => $t->risk_points,
                'risk_flag'                 => $t->risk_flag,
                'required'                  => $t->required,
                'conditional_on_question_id' => $t->conditional_on_question_id,
                'conditional_show_when'     => $t->conditional_show_when,
                'sort_order'                => $t->sort_order,
                'current_answer'            => $answers[$t->id] ?? null,
                'current_note'              => $notes[$t->id] ?? null,
            ]),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function serialize(KycCase $c): array
    {
        return [
            'id'             => $c->id,
            'case_number'    => $c->case_number,
            'status'         => $c->status,
            'risk_score'     => $c->risk_score,
            'risk_level'     => $c->risk_level,
            'blocking'       => $c->blocking,
            'buyer_name'     => $c->buyer_name,
            'buyer_email'    => $c->buyer_email,
            'seller_name'    => $c->seller_name,
            'boat_name'      => $c->boat_name,
            'deal_value'     => $c->deal_value,
            'location'       => $c->location ? ['id' => $c->location->id, 'name' => $c->location->name] : null,
            'yacht'          => $c->yacht ? ['id' => $c->yacht->id, 'name' => $c->yacht->boat_name] : null,
            'auto_created'   => $c->auto_created,
            'created_at'     => $c->created_at?->toIso8601String(),
            'approved_at'    => $c->approved_at?->toIso8601String(),
        ];
    }

    private function serializeFull(KycCase $c): array
    {
        return array_merge($this->serialize($c), [
            'offer_id'         => $c->offer_id,
            'deal_id'          => $c->deal_id,
            'contract_id'      => $c->contract_id,
            'buyer_phone'      => $c->buyer_phone,
            'buyer_address'    => $c->buyer_address,
            'buyer_city'       => $c->buyer_city,
            'buyer_country'    => $c->buyer_country,
            'buyer_iban'       => $c->buyer_iban,
            'seller_phone'     => $c->seller_phone,
            'seller_email'     => $c->seller_email,
            'seller_address'   => $c->seller_address,
            'boat_type'        => $c->boat_type,
            'boat_value'       => $c->boat_value,
            'payment_method'   => $c->payment_method,
            'notes'            => $c->notes,
            'rejection_reason' => $c->rejection_reason,
            'approved_by'      => $c->approvedBy ? ['id' => $c->approvedBy->id, 'name' => $c->approvedBy->name] : null,
            'broker'           => $c->broker ? ['id' => $c->broker->id, 'name' => $c->broker->name] : null,
            'answers'          => $c->answers->map(fn ($a) => [
                'id'                 => $a->id,
                'question_id'        => $a->question_template_id,
                'answer'             => $a->answer,
                'note'               => $a->note,
                'risk_points_awarded' => $a->risk_points_awarded,
                'answered_by'        => $a->answeredBy?->name,
                'answered_at'        => $a->answered_at?->toIso8601String(),
            ]),
            'documents'  => $c->documents->map(fn ($d) => [
                'id'            => $d->id,
                'party'         => $d->party,
                'document_type' => $d->document_type,
                'original_name' => $d->original_name,
                'size'          => $d->size,
                'verified'      => $d->verified,
                'verified_by'   => $d->verifiedBy?->name,
                'verified_at'   => $d->verified_at?->toIso8601String(),
                'uploaded_by'   => $d->uploadedBy?->name,
                'uploaded_at'   => $d->created_at?->toIso8601String(),
            ]),
            'timeline' => $c->timeline->map(fn ($t) => [
                'id'          => $t->id,
                'event'       => $t->event,
                'description' => $t->description,
                'actor_name'  => $t->actor_name ?? $t->actor?->name,
                'meta'        => $t->meta,
                'created_at'  => $t->created_at?->toIso8601String(),
            ]),
        ]);
    }

    private function serializeSections($sections, KycCase $kycCase): array
    {
        $answers = $kycCase->answers->keyBy('question_template_id');
        $result  = [];

        foreach ($sections as $sectionName => $questions) {
            $result[$sectionName] = $questions->map(fn ($q) => [
                'id'                         => $q->id,
                'question'                   => $q->question,
                'action'                     => $q->action,
                'field_type'                 => $q->field_type,
                'options'                    => $q->options,
                'risk_points'                => $q->risk_points,
                'risk_flag'                  => $q->risk_flag,
                'required'                   => $q->required,
                'conditional_on_question_id' => $q->conditional_on_question_id,
                'conditional_show_when'      => $q->conditional_show_when,
                'current_answer'             => $answers[$q->id]?->answer,
                'current_note'               => $answers[$q->id]?->note,
                'risk_points_awarded'        => $answers[$q->id]?->risk_points_awarded ?? 0,
            ])->values();
        }

        return $result;
    }

    private function audit(string $action, KycCase $c, Request $request, array $meta = []): void
    {
        try {
            AuditLog::create([
                'action'      => $action,
                'risk_level'  => match($c->risk_level) {
                    'critical', 'high' => 'high',
                    'medium' => 'medium',
                    default  => 'low',
                },
                'result'      => 'success',
                'actor_id'    => $request->user()?->id,
                'entity_type' => 'kyc_case',
                'entity_id'   => $c->id,
                'location_id' => $c->location_id,
                'meta'        => array_merge($meta, ['case_number' => $c->case_number]),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[KYC] Audit failed: ' . $e->getMessage());
        }
    }
}
