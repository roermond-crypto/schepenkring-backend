<?php

namespace App\Services;

use App\Models\Yacht;

class BoatImagePublicationStatusService
{
    public function summary(Yacht $yacht): array
    {
        $images = $yacht->images()
            ->where('status', '!=', 'deleted')
            ->get();

        $approved = $images->whereIn('status', ['approved', 'ready_for_review'])->count();
        $processing = $images->whereIn('status', ['uploaded', 'processing'])->count();
        $rejected = $images->where('status', 'rejected')->count();
        $minApproved = (int) config('services.pipeline.min_approved_images', 1);

        $status = $images->isNotEmpty() && $approved >= $minApproved && $processing === 0 && $rejected === 0
            ? 'ready'
            : 'incomplete';

        return [
            'status' => $status,
            'approved_images' => $approved,
            'review_required_images' => 0,
            'rejected_images' => $rejected,
            'processing_images' => $processing,
            'total_images' => $images->count(),
            'min_approved_images' => $minApproved,
        ];
    }
}
