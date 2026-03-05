<?php

namespace App\Http\Controllers;

use App\Models\PartnerContract;
use App\Services\OnboardingService;
use App\Services\SignhostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartnerContractController extends Controller
{
    public function create(Request $request, OnboardingService $onboarding)
    {
        $user = $request->user();
        if (!$user || strtolower((string) $user->role) !== 'partner') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (strtolower((string) $user->status) !== 'contract_pending') {
            return response()->json(['message' => 'Contract step not available'], 403);
        }

        $path = $this->generateSimplePdf($user);
        $sha = hash_file('sha256', Storage::path($path));

        $signhost = new SignhostService();
        $result = $signhost->createSingleSignerTransaction($user, Storage::path($path), 'partner-' . $user->id);
        $transaction = $result['transaction'] ?? [];
        $signingUrl = $this->extractSigningUrl($transaction);

        $record = PartnerContract::create([
            'user_id' => $user->id,
            'signhost_transaction_id' => $result['transaction_id'],
            'status' => 'pending',
            'contract_pdf_path' => $path,
            'contract_sha256' => $sha,
        ]);

        $onboarding->markStep($user, 'contract', $request);

        return response()->json([
            'message' => 'Contract signing started',
            'transaction_id' => $record->signhost_transaction_id,
            'signing_url' => $signingUrl,
            'contract_pdf_path' => $record->contract_pdf_path,
            'contract_sha256' => $record->contract_sha256,
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower((string) $user->role) !== 'partner') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $contract = PartnerContract::where('user_id', $user->id)->latest()->first();
        if (!$contract) {
            return response()->json(['message' => 'No contract found'], 404);
        }

        return response()->json($contract);
    }

    private function generateSimplePdf($user): string
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\n2 0 obj<< /Length 64>>stream\nPartner {$user->id} contract\nendstream\nendobj\n3 0 obj<< /Type /Page /Parent 4 0 R /Contents 2 0 R>>endobj\n4 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj\n5 0 obj<< /Type /Catalog /Pages 4 0 R>>endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000050 00000 n \n0000000120 00000 n \n0000000180 00000 n \n0000000240 00000 n \ntrailer<< /Size 6 /Root 5 0 R>>\nstartxref\n300\n%%EOF";

        $path = "contracts/partner_{$user->id}.pdf";
        Storage::put($path, $content);
        return $path;
    }

    private function extractSigningUrl(array $transaction): ?string
    {
        $signers = $transaction['Signers'] ?? $transaction['signers'] ?? [];
        if (is_array($signers) && count($signers) > 0) {
            return $signers[0]['SignUrl'] ?? $signers[0]['signUrl'] ?? null;
        }

        return null;
    }
}
