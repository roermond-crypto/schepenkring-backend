<?php

namespace App\Actions\Signhost;

use App\Enums\RiskLevel;
use App\Models\ContractTemplate;
use App\Models\SignDocument;
use App\Models\SignRequest;
use App\Models\User;
use App\Repositories\SignRequestRepository;
use App\Services\ActionSecurity;
use App\Services\ContractTemplateRendererService;
use App\Services\LocationAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateContractAction
{
    public function __construct(
        private SignRequestRepository $signRequests,
        private LocationAccessService $locationAccess,
        private ActionSecurity $security,
        private ContractTemplateRendererService $renderer,
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
            // No PDFs provided — render from contract template if specified, else placeholder
            $pdfFiles = [null];
        }

        $contractPaths = [];
        $contractHashes = [];
        $allDocumentMetadata = [];

        $contractTemplateId = isset($data['contract_template_id']) ? (int) $data['contract_template_id'] : null;

        foreach ($pdfFiles as $index => $pdf) {
            [$content, $fileName, $documentMetadata] = $this->resolveContractPdf(
                $pdf,
                $entityType,
                $entityId,
                $title,
                $index,
                $contractTemplateId,
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
    private function resolveContractPdf(mixed $pdf, string $entityType, int $entityId, string $title, int $index = 0, ?int $contractTemplateId = null): array
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
                    'contract_source'            => 'upload',
                    'contract_original_filename' => $originalName,
                ],
            ];
        }

        // If a contract template ID was given, render it as the PDF
        if ($contractTemplateId) {
            $template = ContractTemplate::find($contractTemplateId);
            if ($template) {
                $tags    = $this->buildTemplateTags($entityType, $entityId);
                $content = $this->renderTemplatePdf($template, $tags);
                $ext     = class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) ? 'pdf' : 'html';
                $fileName = Str::slug($entityType) . "_{$entityId}_{$timestamp}{$suffix}_template_{$contractTemplateId}.{$ext}";

                return [
                    $content,
                    $fileName,
                    [
                        'contract_source'      => 'template',
                        'contract_template_id' => $contractTemplateId,
                        'contract_template_name' => $template->name,
                    ],
                ];
            }
        }

        // Fallback: minimal valid placeholder PDF
        return [
            $this->buildPdf($entityType, $entityId, $title),
            Str::slug($entityType) . "_{$entityId}_{$timestamp}{$suffix}.pdf",
            [
                'contract_source' => 'generated',
            ],
        ];
    }

    /**
     * Render a ContractTemplate to a PDF binary string using DomPDF.
     * Falls back to returning the rendered HTML (for browser printing) if DomPDF is unavailable.
     *
     * @return string  Binary PDF content (or rendered HTML if DomPDF not installed)
     */
    private function renderTemplatePdf(ContractTemplate $template, array $tags): string
    {
        $html = $this->renderer->replaceTags($template->content_html, $tags);
        $fullHtml = $this->buildPrintHtml($html, $template->name);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            try {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($fullHtml);
                $pdf->setPaper('A4', 'portrait');
                return $pdf->output();
            } catch (\Throwable $e) {
                Log::error('[GenerateContractAction] DomPDF render failed, falling back to HTML', [
                    'template_id' => $template->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // DomPDF not available — return HTML so it can at least be viewed
        return $fullHtml;
    }

    private function buildPrintHtml(string $body, string $title): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>
  @page { margin: 25mm 20mm; size: A4; }
  body { font-family: Arial, sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.7; }
  h1 { font-size: 16pt; font-weight: bold; margin-bottom: 8pt; }
  h2 { font-size: 13pt; font-weight: bold; margin-top: 16pt; margin-bottom: 6pt; }
  h3 { font-size: 11pt; font-weight: bold; margin-top: 12pt; margin-bottom: 4pt; }
  p  { margin: 6pt 0; }
  hr { border: none; border-top: 1px solid #ccc; margin: 16pt 0; }
  strong { font-weight: bold; }
  em { font-style: italic; }
  u  { text-decoration: underline; }
  ul, ol { padding-left: 18pt; margin: 6pt 0; }
  li { margin: 3pt 0; }
</style>
</head>
<body>{$body}</body>
</html>
HTML;
    }

    private function buildPdf(string $entityType, int $entityId, string $title): string
    {
        // Minimal valid placeholder — tells Signhost a PDF exists without real content
        $text = "{$title} | {$entityType} #{$entityId} | " . now()->toDateTimeString();
        $len  = strlen($text);

        return "%PDF-1.4\n" .
               "1 0 obj<</Type /Catalog /Pages 2 0 R>>endobj\n" .
               "2 0 obj<</Type /Pages /Kids [3 0 R] /Count 1>>endobj\n" .
               "3 0 obj<</Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources<</Font<</F1 5 0 R>>>>>>endobj\n" .
               "4 0 obj<</Length {$len}>>stream\n" .
               "BT /F1 12 Tf 72 750 Td ({$text}) Tj ET\n" .
               "endstream\nendobj\n" .
               "5 0 obj<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>endobj\n" .
               "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000266 00000 n \n0000000360 00000 n \n" .
               "trailer<</Size 6 /Root 1 0 R>>\nstartxref\n430\n%%EOF";
    }

    /**
     * Build tag values for a given contract template context.
     * Uses yacht tags when entity_type=Yacht, otherwise returns empty array.
     */
    private function buildTemplateTags(string $entityType, int $entityId): array
    {
        if (strtolower($entityType) === 'yacht') {
            try {
                return $this->renderer->resolveTagsForYacht($entityId);
            } catch (\Throwable $e) {
                Log::warning('[GenerateContractAction] Failed to resolve yacht tags for template', [
                    'entity_id' => $entityId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $this->renderer->getSampleTags();
    }
}