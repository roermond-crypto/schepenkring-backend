<?php

namespace App\Services;

use App\Models\BoatIntake;

class BoatIntakeScoreService
{
    // Configurable thresholds (could later come from admin settings)
    private const PHOTO_TARGET         = 12;
    private const DESCRIPTION_TARGET   = 500;
    private const FIELD_WEIGHTS = [
        // Contact
        'seller_first_name' => 2,
        'seller_last_name'  => 2,
        'seller_email'      => 3,
        'seller_phone'      => 2,
        'seller_address'    => 2,
        'seller_city'       => 1,
        'seller_postal_code' => 1,
        // Boat
        'boat_brand'        => 5,
        'boat_model'        => 5,
        'boat_name'         => 3,
        'build_year'        => 5,
        'length_m'          => 4,
        'width_m'           => 2,
        'draft_m'           => 2,
        'asking_price'      => 6,
        'vat_status'        => 5,
        'ce_category'       => 5,
        'boat_type'         => 3,
        'location_id'       => 4,
    ];

    private const DOCUMENT_POINTS = [
        'ce_certificate'  => 10,
        'vat_document'    => 8,
        'invoice'         => 5,
        'registration'    => 6,
        'maintenance'     => 4,
        'other'           => 2,
    ];

    public function calculate(BoatIntake $intake): array
    {
        // ── Field score (0-100) ─────────────────────────────
        $totalFieldWeight = array_sum(self::FIELD_WEIGHTS);
        $earnedWeight = 0;
        foreach (self::FIELD_WEIGHTS as $field => $weight) {
            if (! empty($intake->$field)) {
                $earnedWeight += $weight;
            }
        }
        $fieldScore = round(($earnedWeight / $totalFieldWeight) * 100);

        // ── Photo score (0-100) ─────────────────────────────
        $photoCount = $intake->photo_count;
        $photoScore = min(100, round(($photoCount / self::PHOTO_TARGET) * 100));

        // ── Description score (0-100) ───────────────────────
        $descLen = $intake->description_length;
        $descScore = min(100, round(($descLen / self::DESCRIPTION_TARGET) * 100));

        // ── Document score (0-100) ──────────────────────────
        $docTypes = $intake->documents()->pluck('type')->unique()->toArray();
        $docPoints = 0;
        foreach (self::DOCUMENT_POINTS as $type => $points) {
            if (in_array($type, $docTypes)) {
                $docPoints += $points;
            }
        }
        $maxDocPoints = array_sum(self::DOCUMENT_POINTS);
        $docScore = round(($docPoints / $maxDocPoints) * 100);

        // ── Total score (weighted average) ──────────────────
        $total = round(
            ($fieldScore * 0.40) +
            ($photoScore * 0.30) +
            ($descScore * 0.15) +
            ($docScore  * 0.15)
        );

        // ── Missing items ────────────────────────────────────
        $missing = [];
        if ($photoCount < self::PHOTO_TARGET) {
            $missing[] = ['key' => 'more_photos', 'label' => "Meer foto's ({$photoCount}/" . self::PHOTO_TARGET . ')', 'severity' => 'warning'];
        }
        if ($descLen < self::DESCRIPTION_TARGET) {
            $missing[] = ['key' => 'description', 'label' => "Langere beschrijving ({$descLen}/" . self::DESCRIPTION_TARGET . " tekens)", 'severity' => 'warning'];
        }
        if (empty($intake->vat_status) || $intake->vat_status === 'unknown') {
            $missing[] = ['key' => 'vat_status', 'label' => 'BTW-status ontbreekt', 'severity' => 'required'];
        }
        if (empty($intake->ce_category) || $intake->ce_category === 'unknown') {
            $missing[] = ['key' => 'ce_category', 'label' => 'CE-categorie ontbreekt', 'severity' => 'required'];
        }
        if (! in_array('ce_certificate', $docTypes)) {
            $missing[] = ['key' => 'ce_certificate', 'label' => 'CE-certificaat ontbreekt', 'severity' => 'important'];
        }
        if (! in_array('vat_document', $docTypes)) {
            $missing[] = ['key' => 'vat_document', 'label' => 'BTW-document ontbreekt', 'severity' => 'important'];
        }

        return [
            'total'       => $total,
            'breakdown'   => [
                'fields'      => $fieldScore,
                'photos'      => $photoScore,
                'description' => $descScore,
                'documents'   => $docScore,
            ],
            'photo_count'      => $photoCount,
            'photo_target'     => self::PHOTO_TARGET,
            'description_length' => $descLen,
            'description_target' => self::DESCRIPTION_TARGET,
            'missing'          => $missing,
        ];
    }

    public function deriveStatus(BoatIntake $intake, array $score): string
    {
        // Minimum required fields: name, email, location, boat brand+model, price
        $hasRequired =
            ! empty($intake->seller_first_name) &&
            ! empty($intake->seller_email) &&
            ! empty($intake->location_id) &&
            ! empty($intake->boat_brand) &&
            ! empty($intake->asking_price);

        if (! $hasRequired) {
            return 'frontend_draft';
        }

        if ($score['total'] < 60 || ! empty($score['missing'])) {
            return 'missing_information';
        }

        return 'ready_for_admin_review';
    }
}
