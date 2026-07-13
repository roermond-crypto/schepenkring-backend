<?php

namespace App\Services;

use App\Models\BoatFieldChange;

/**
 * Backs the AI Quality Dashboard. Grounded strictly in what
 * `boat_field_changes` actually records — one row per value transition,
 * attributed to one `changed_by_type`. There is no "accepted AI suggestion"
 * signal in the schema (a suggestion left untouched never produces a row),
 * so this deliberately does NOT report an accepted/rejected split. What IS
 * derivable and reported instead:
 *   - AI-authored changes: changed_by_type = 'ai'
 *   - Manual corrections: a human (user/admin) changed a field that had an
 *     AI-assigned confidence_before — i.e. an AI-scored value a person
 *     overrode. This is the real "AI got it wrong, human fixed it" signal.
 */
class AiQualityService
{
    private const HUMAN_TYPES = ['user', 'admin'];

    public function summary(?int $platformId): array
    {
        $base = fn () => BoatFieldChange::query()->when(
            $platformId !== null,
            fn ($q) => $platformId === 0 ? $q->whereNull('platform_id') : $q->where('platform_id', $platformId)
        );

        $manualCorrections = fn () => $base()
            ->whereIn('changed_by_type', self::HUMAN_TYPES)
            ->whereNotNull('confidence_before');

        return [
            'avg_confidence_before' => $this->round($base()->whereNotNull('confidence_before')->avg('confidence_before')),
            'avg_confidence_after' => $this->round($base()->whereNotNull('confidence_after')->avg('confidence_after')),
            'avg_confidence_improvement' => $this->round(
                $manualCorrections()->whereNotNull('confidence_after')
                    ->selectRaw('AVG(confidence_after - confidence_before) as avg_improvement')
                    ->value('avg_improvement')
            ),
            'ai_authored_count' => $base()->where('changed_by_type', 'ai')->count(),
            'manual_corrections_count' => $manualCorrections()->count(),
            'most_corrected_fields' => $manualCorrections()
                ->select('field_name')
                ->selectRaw('COUNT(*) as corrections')
                ->groupBy('field_name')
                ->orderByDesc('corrections')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['field_name' => $row->field_name, 'corrections' => (int) $row->corrections]),
            'lowest_confidence_fields' => $base()
                ->whereNotNull('confidence_before')
                ->select('field_name')
                ->selectRaw('AVG(confidence_before) as avg_confidence, COUNT(*) as samples')
                ->groupBy('field_name')
                ->havingRaw('COUNT(*) >= 1')
                ->orderBy('avg_confidence')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'field_name' => $row->field_name,
                    'avg_confidence' => $this->round($row->avg_confidence),
                    'samples' => (int) $row->samples,
                ]),
            'platform_breakdown' => $platformId === null ? $this->platformBreakdown() : null,
        ];
    }

    private function platformBreakdown(): array
    {
        $rows = BoatFieldChange::query()
            ->whereIn('changed_by_type', self::HUMAN_TYPES)
            ->whereNotNull('confidence_before')
            ->select('platform_id')
            ->selectRaw('COUNT(*) as corrections')
            ->with('platform:id,name')
            ->groupBy('platform_id')
            ->orderByDesc('corrections')
            ->get();

        return $rows->map(fn ($row) => [
            'platform_id' => $row->platform_id,
            'platform_name' => $row->platform_id === null ? null : $row->platform?->name,
            'corrections' => (int) $row->corrections,
        ])->values()->all();
    }

    /**
     * The learning-feedback view: rows where a human overrode an
     * AI-scored value. Every column needed to feed a future training
     * pipeline (original value, final value, confidence, corrector,
     * timestamp, reason) already exists on the row itself.
     */
    public function feedback(?int $platformId, int $perPage)
    {
        $query = BoatFieldChange::with(['user:id,name,email,avatar', 'yacht:id,boat_name', 'platform:id,name'])
            ->whereIn('changed_by_type', self::HUMAN_TYPES)
            ->whereNotNull('confidence_before')
            ->orderByDesc('created_at');

        if ($platformId === 0) {
            $query->whereNull('platform_id');
        } elseif ($platformId !== null) {
            $query->where('platform_id', $platformId);
        }

        return $query->paginate($perPage);
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 4);
    }
}
