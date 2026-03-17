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

        // Collect all PDFs: support both single 'pdf' and multiple 'pdfs' fields
        $pdfFiles = $this->collectPdfFiles($data);

        if (empty($pdfFiles)) {
            // No PDFs provided — generate a placeholder PDF (backward compatible)
            $pdfFiles = [null];
        }

        $contractPaths = [];
        $contractHashes = [];
        $allDocumentMetadata = [];

        foreach ($pdfFiles as $index => $pdf) {
            [$content, $fileName, $documentMetadata] = $this->resolveContractPdf(
                $pdf,
                $entityType,
                $entityId,
                $title,
                $index
            );
            $path = "contracts/{$fileName}";

            Storage::disk('public')->put($path, $content);
            $sha256 = hash('sha256', $content);

            $contractPaths[] = $path;
            $contractHashes[] = $sha256;
            $allDocumentMetadata[] = $documentMetadata;
        }

        // For backward compatibility, the first PDF is stored as the primary contract
        $primaryPath = $contractPaths[0];
        $primaryHash = $contractHashes[0];
        $primaryDocMeta = $allDocumentMetadata[0];

        $request = $this->signRequests->create([
            'location_id' => $locationId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'provider' => 'signhost',
            'status' => 'DRAFT',
            'metadata' => array_merge($data['metadata'] ?? [], $primaryDocMeta, [
                'contract_pdf_path' => $primaryPath,
                'contract_sha256' => $primaryHash,
                'contract_pdf_paths' => $contractPaths,
                'contract_sha256s' => $contractHashes,
            ]),
        ]);

        // Create a SignDocument for each PDF
        foreach ($contractPaths as $i => $path) {
            SignDocument::create([
                'sign_request_id' => $request->id,
                'file_path' => $path,
                'sha256' => $contractHashes[$i],
                'type' => 'original',
            ]);
        }

        $this->security->log('contract.generate', RiskLevel::MEDIUM, $actor, $request, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'document_count' => count($contractPaths),
        ], [
            'location_id' => $locationId,
            'snapshot_after' => $request->toArray(),
        ]);

        return $request->load('documents');
    }

    /**
     * Collect PDF files from the request data, merging single 'pdf' and array 'pdfs'.
     *
     * @return array<int, UploadedFile|null>
     */
    private function collectPdfFiles(array $data): array
    {
        $files = [];

        // Support the legacy single 'pdf' field
        if (! empty($data['pdf']) && $data['pdf'] instanceof UploadedFile) {
            $files[] = $data['pdf'];
        }

        // Support the new 'pdfs' array field
        if (! empty($data['pdfs']) && is_array($data['pdfs'])) {
            foreach ($data['pdfs'] as $pdf) {
                if ($pdf instanceof UploadedFile) {
                    $files[] = $pdf;
                }
            }
        }

        return $files;
    }

    /**
     * @return array{0:string,1:string,2:array<string,mixed>}
     */
    private function resolveContractPdf(mixed $pdf, string $entityType, int $entityId, string $title, int $index = 0): array
    {
        $timestamp = now()->format('Ymd_His');
        $suffix = $index > 0 ? "_{$index}" : '';

        if ($pdf instanceof UploadedFile) {
            $content = file_get_contents($pdf->getRealPath());
            if ($content === false) {
                throw new \RuntimeException('Failed to read uploaded contract PDF');
            }

            $originalName = $pdf->getClientOriginalName();
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'uploaded-contract';
            $fileName = Str::slug($entityType) . "_{$entityId}_{$timestamp}{$suffix}_{$baseName}.pdf";

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
            Str::slug($entityType) . "_{$entityId}_{$timestamp}{$suffix}.pdf",
            [
                'contract_source' => 'generated',
            ],
        ];
    }

    private function buildPdf(string $entityType, int $entityId, string $title): string
    {
        $text = "{$title} for {$entityType} {$entityId} generated at " . now()->toDateTimeString();

        return "%PDF-1.4\n1 0 obj<<>>endobj\n2 0 obj<< /Length 128>>stream\n{$text}\nendstream\nendobj\n3 0 obj<< /Type /Page /Parent 4 0 R /Contents 2 0 R>>endobj\n4 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj\n5 0 obj<< /Type /Catalog /Pages 4 0 R>>endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000050 00000 n \n0000000140 00000 n \n0000000200 00000 n \n0000000260 00000 n \ntrailer<< /Size 6 /Root 5 0 R>>\nstartxref\n320\n%%EOF";
    }
}