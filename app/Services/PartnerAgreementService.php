<?php

namespace App\Services;

class PartnerAgreementService
{
    public function currentVersion(): string
    {
        return (string) config('legal.partner_agreement.version', 'v1');
    }

    public function currentText(): string
    {
        $path = config('legal.partner_agreement.path', resource_path('legal/partner_agreement_v1.txt'));
        if (is_string($path) && file_exists($path)) {
            return (string) file_get_contents($path);
        }

        return 'Partner agreement unavailable.';
    }
}
