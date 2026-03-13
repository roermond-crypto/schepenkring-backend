<?php

namespace App\Actions\Signhost;

use App\Enums\RiskLevel;
use App\Models\SignDocument;
use App\Models\SignRequest;
use App\Models\User;
use App\Repositories\SignRequestRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateContractAction
{
    public function __construct(
        private SignRequestRepository $signRequests,
        private LocationAccessService $locationAccess,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, array $data): SignRequest
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $locationId = (int) $data['location_id'];

        if (! $actor->isAdmin()) {
            if (! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $entityType = $data['entity_type'];
        $entityId = (int) $data['entity_id'];
        $title = $data['title'] ?? 'Contract';

        [$content, $fileName, $documentMetadata] = $this->resolveContractPdf(
            $data['pdf'] ?? null,
            $entityType,
            $entityId,
            $title
        );
        $path = "contracts/{$fileName}";

        Storage::disk('public')->put($path, $content);
        $sha256 = hash('sha256', $content);

        $request = $this->signRequests->create([
            'location_id' => $locationId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'provider' => 'signhost',
            'status' => 'DRAFT',
            'metadata' => array_merge($data['metadata'] ?? [], $documentMetadata, [
                'contract_pdf_path' => $path,
                'contract_sha256' => $sha256,
            ]),
        ]);

        SignDocument::create([
            'sign_request_id' => $request->id,
            'file_path' => $path,
            'sha256' => $sha256,
            'type' => 'original',
        ]);

        $this->security->log('contract.generate', RiskLevel::MEDIUM, $actor, $request, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], [
            'location_id' => $locationId,
            'snapshot_after' => $request->toArray(),
        ]);

        return $request->load('documents');
    }

    /**
     * @return array{0:string,1:string,2:array<string,mixed>}
     */
    private function resolveContractPdf(mixed $pdf, string $entityType, int $entityId, string $title): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($pdf instanceof UploadedFile) {
            $content = file_get_contents($pdf->getRealPath());
            if ($content === false) {
                throw new \RuntimeException('Failed to read uploaded contract PDF');
            }

            $originalName = $pdf->getClientOriginalName();
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'uploaded-contract';
            $fileName = Str::slug($entityType)."_{$entityId}_{$timestamp}_{$baseName}.pdf";

            return [
                $content,
                $fileName,
                [
                    'contract_source' => 'upload',
                    'contract_original_filename' => $originalName,
                ],
            ];
        }

        return [
            $this->buildPdf($entityType, $entityId, $title),
            Str::slug($entityType)."_{$entityId}_{$timestamp}.pdf",
            [
                'contract_source' => 'generated',
            ],
        ];
    }

    private function buildPdf(string $entityType, int $entityId, string $title): string
    {
        $text = "{$title} for {$entityType} {$entityId} generated at ".now()->toDateTimeString();

        return "%PDF-1.4\n1 0 obj<<>>endobj\n2 0 obj<< /Length 128>>stream\n{$text}\nendstream\nendobj\n3 0 obj<< /Type /Page /Parent 4 0 R /Contents 2 0 R>>endobj\n4 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj\n5 0 obj<< /Type /Catalog /Pages 4 0 R>>endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000050 00000 n \n0000000140 00000 n \n0000000200 00000 n \n0000000260 00000 n \ntrailer<< /Size 6 /Root 5 0 R>>\nstartxref\n320\n%%EOF";
    }
}
