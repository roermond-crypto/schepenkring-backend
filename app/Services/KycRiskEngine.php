<?php

namespace App\Services;

use App\Models\KycCase;
use App\Models\KycAnswer;
use App\Models\KycQuestionTemplate;

class KycRiskEngine
{
    // Risk level thresholds
    private const THRESHOLDS = [
        'critical' => 100,
        'high'     => 61,
        'medium'   => 21,
    ];

    public function recalculate(KycCase $kycCase): array
    {
        $totalPoints = 0;

        // Sum risk_points_awarded from all current answers
        $totalPoints = (int) KycAnswer::where('kyc_case_id', $kycCase->id)
            ->sum('risk_points_awarded');

        $level = $this->resolveLevel($totalPoints);

        $kycCase->update([
            'risk_score' => $totalPoints,
            'risk_level' => $level,
        ]);

        // Auto-escalate status if high risk
        if ($totalPoints >= self::THRESHOLDS['high'] && ! in_array($kycCase->status, ['approved', 'rejected', 'reported'])) {
            $kycCase->update(['status' => 'high_risk']);
        }

        return ['score' => $totalPoints, 'level' => $level];
    }

    public function resolveLevel(int $score): string
    {
        if ($score >= self::THRESHOLDS['critical']) return 'critical';
        if ($score >= self::THRESHOLDS['high'])     return 'high';
        if ($score >= self::THRESHOLDS['medium'])   return 'medium';
        return 'low';
    }

    // Calculate how many points a specific answer awards
    public function pointsForAnswer(KycQuestionTemplate $question, string $answer): int
    {
        // Yes/no questions award points on "yes" (positive trigger)
        if ($question->field_type === 'yes_no') {
            $normalized = strtolower(trim($answer));
            if (in_array($normalized, ['yes', 'ja', 'true', '1'])) {
                return $question->risk_points;
            }
            return 0;
        }

        // For other types, award full points if non-empty answer given
        if ($question->risk_points > 0 && trim($answer) !== '') {
            return $question->risk_points;
        }

        return 0;
    }

    public static function levelColor(string $level): string
    {
        return match($level) {
            'critical' => '#7f1d1d',
            'high'     => '#dc2626',
            'medium'   => '#d97706',
            default    => '#16a34a',
        };
    }

    public static function levelBadge(string $level): string
    {
        return match($level) {
            'critical' => 'Kritiek',
            'high'     => 'Hoog',
            'medium'   => 'Gemiddeld',
            default    => 'Laag',
        };
    }

    public function checkBlocking(KycCase $kycCase): bool
    {
        $blockingAnswers = KycAnswer::where('kyc_case_id', $kycCase->id)
            ->whereHas('question', fn ($q) => $q->where('risk_flag', 'blocking')
                ->orWhere('risk_flag', 'critical'))
            ->where(function ($q) {
                $q->where('answer', 'yes')->orWhere('answer', 'ja')->orWhere('answer', '1');
            })
            ->exists();

        if ($blockingAnswers && ! $kycCase->blocking) {
            $kycCase->update(['blocking' => true]);
        }

        return $blockingAnswers;
    }
}
