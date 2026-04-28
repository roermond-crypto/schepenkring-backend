<?php

namespace App\Services;

use App\Models\SellerOnboarding;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Storage;

class SellerOnboardingContractPdfService
{
    public function generate(SellerOnboarding $onboarding, SellerProfile $profile): array
    {
        $user = $onboarding->user;
        $sellerType = $profile->seller_type ?: 'private';
        
        // Using a basic text-based PDF placeholder like NauticSecure
        $content = implode("\n", [
            'Seller Onboarding Agreement',
            'Contract Version: v1',
            'Onboarding ID: ' . $onboarding->id,
            'Seller Type: ' . $sellerType,
            'Seller Name: ' . ($profile->full_name ?: $user?->name ?: ''),
            'Seller Email: ' . ($profile->email ?: $user?->email ?: ''),
            'Seller Phone: ' . ($profile->phone ?: ''),
            'IBAN: ' . ($profile->iban ?: ''),
            'Company Name: ' . ($profile->company_name ?: ''),
            'KvK Number: ' . ($profile->kvk_number ?: ''),
            'Generated At: ' . now()->toIso8601String(),
            'Copyright Schepenkring. This agreement confirms marketplace terms, compliance consent, privacy consent, and payout verification obligations.',
        ]);

        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\n2 0 obj<< /Length " . strlen($content) . " >>stream\n" . $content . "\nendstream\nendobj\n3 0 obj<< /Type /Page /Parent 4 0 R /Contents 2 0 R>>endobj\n4 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj\n5 0 obj<< /Type /Catalog /Pages 4 0 R>>endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000050 00000 n \n0000000120 00000 n \n0000000180 00000 n \n0000000240 00000 n \ntrailer<< /Size 6 /Root 5 0 R>>\nstartxref\n300\n%%EOF";

        $path = sprintf('contracts/seller_onboarding_%s_%s.pdf', $onboarding->id, now()->format('YmdHis'));
        Storage::disk('public')->put($path, $pdf);

        return [
            'path' => $path,
            'sha256' => hash('sha256', $pdf),
        ];
    }
}
