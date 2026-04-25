<?php

namespace App\Services;

use App\Models\User;

class SellerPublishGateService
{
    public function assessForUser(?User $user): array
    {
        if (! $user) {
            return [
                'allowed' => false,
                'message' => 'Unauthorized.',
                'status' => null,
                'onboarding' => null,
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
            'status' => 'approved',
            'onboarding' => null,
        ];
    }
}
