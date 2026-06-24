<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KycCase extends Model
{
    use SoftDeletes;

    protected $table = 'kyc_cases';

    protected $fillable = [
        'case_number',
        'deal_id', 'offer_id', 'contract_id',
        'buyer_id', 'seller_id', 'yacht_id', 'location_id', 'broker_id',
        'status', 'risk_score', 'risk_level', 'blocking',
        'approved_by', 'approved_at', 'rejection_reason', 'notes',
        'buyer_name', 'buyer_email', 'buyer_phone', 'buyer_address', 'buyer_city', 'buyer_country', 'buyer_iban', 'buyer_dob',
        'seller_name', 'seller_email', 'seller_phone', 'seller_address',
        'boat_name', 'boat_type', 'boat_value',
        'deal_value', 'payment_method',
        'auto_created',
    ];

    protected $casts = [
        'blocking'     => 'boolean',
        'auto_created' => 'boolean',
        'approved_at'  => 'datetime',
        'risk_score'   => 'integer',
        'boat_value'   => 'float',
        'deal_value'   => 'float',
    ];

    // ── Relations ────────────────────────────────────────────

    public function deal(): BelongsTo       { return $this->belongsTo(Deal::class); }
    public function offer(): BelongsTo      { return $this->belongsTo(Offer::class); }
    public function contract(): BelongsTo   { return $this->belongsTo(Contract::class); }
    public function buyer(): BelongsTo      { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller(): BelongsTo     { return $this->belongsTo(User::class, 'seller_id'); }
    public function yacht(): BelongsTo      { return $this->belongsTo(Yacht::class); }
    public function location(): BelongsTo   { return $this->belongsTo(Location::class); }
    public function broker(): BelongsTo     { return $this->belongsTo(User::class, 'broker_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function answers(): HasMany   { return $this->hasMany(KycAnswer::class); }
    public function documents(): HasMany { return $this->hasMany(KycDocument::class); }
    public function timeline(): HasMany  { return $this->hasMany(KycTimeline::class)->orderBy('created_at'); }

    // ── Helpers ──────────────────────────────────────────────

    public function isDraft(): bool       { return $this->status === 'draft'; }
    public function isApproved(): bool    { return $this->status === 'approved'; }
    public function isHighRisk(): bool    { return in_array($this->status, ['high_risk', 'reported']); }
    public function isBlocking(): bool    { return $this->blocking; }

    public function sectionProgress(): array
    {
        $templates = KycQuestionTemplate::where('active', true)->get();
        $answers   = $this->answers()->pluck('answer', 'question_template_id');

        $sections = [];
        foreach ($templates as $tpl) {
            $sec = $tpl->section;
            if (! isset($sections[$sec])) {
                $sections[$sec] = ['total' => 0, 'answered' => 0];
            }
            $sections[$sec]['total']++;
            if (isset($answers[$tpl->id]) && $answers[$tpl->id] !== null && $answers[$tpl->id] !== '') {
                $sections[$sec]['answered']++;
            }
        }

        $result = [];
        foreach ($sections as $sec => $counts) {
            $result[$sec] = $counts['total'] > 0
                ? (int) round(($counts['answered'] / $counts['total']) * 100)
                : 0;
        }
        return $result;
    }

    public function missingDocumentTypes(): array
    {
        $required = [
            'buyer'  => ['passport', 'proof_of_address'],
            'seller' => ['passport'],
            'boat'   => ['registration'],
        ];

        $uploaded = $this->documents()->pluck('document_type', 'party')->toArray();
        $missing  = [];

        foreach ($required as $party => $types) {
            foreach ($types as $type) {
                $partyDocs = $this->documents()->where('party', $party)->pluck('document_type')->toArray();
                if (! in_array($type, $partyDocs)) {
                    $missing[] = ['party' => $party, 'type' => $type];
                }
            }
        }
        return $missing;
    }

    public function addTimeline(
        string $event,
        ?string $description = null,
        ?User $actor = null,
        ?array $meta = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $mergedMeta = array_filter(array_merge($meta ?? [], [
            'ip'         => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 250) : null,
        ]));

        KycTimeline::create([
            'kyc_case_id' => $this->id,
            'event'       => $event,
            'description' => $description,
            'actor_id'    => $actor?->id,
            'actor_name'  => $actor ? "{$actor->name}" : null,
            'meta'        => $mergedMeta ?: null,
        ]);
    }
}
