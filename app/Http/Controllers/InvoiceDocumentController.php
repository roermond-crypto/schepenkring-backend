<?php

namespace App\Http\Controllers;

use App\Models\InvoiceDocument;
use App\Models\InvoiceField;
use App\Models\InvoiceStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvoiceDocumentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in([InvoiceDocument::TYPE_INCOMING, InvoiceDocument::TYPE_OUTGOING])],
            'status' => ['nullable', Rule::in($this->allowedStatuses())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InvoiceDocument::query()->with('fields');

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $perPage = $validated['per_page'] ?? 25;

        return response()->json($query->orderByDesc('id')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $allowedRetention = $this->allowedRetentionYears();

        $validated = $request->validate([
            'type' => ['required', Rule::in([InvoiceDocument::TYPE_INCOMING, InvoiceDocument::TYPE_OUTGOING])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,tiff,tif', 'max:' . config('invoices.max_upload_kb')],
            'retention_years' => ['nullable', 'integer', Rule::in($allowedRetention)],
            'metadata' => ['nullable', 'array'],
        ]);

        $file = $request->file('file');
        $hashAlgo = 'sha256';
        $fileHash = hash_file($hashAlgo, $file->getRealPath());
        $disk = config('invoices.storage_disk', config('filesystems.default', 'local'));

        $extension = $file->getClientOriginalExtension();
        if (!$extension) {
            $extension = 'pdf';
        }

        $directory = 'invoices/' . now()->format('Y/m');
        $filename = $fileHash . '-' . Str::uuid()->toString() . '.' . $extension;

        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);
        if (!$path) {
            return response()->json(['message' => 'Failed to store invoice document.'], 500);
        }

        $retentionYears = $validated['retention_years'] ?? $allowedRetention[0];
        $retentionUntil = now()->addYears($retentionYears)->toDateString();

        try {
            $document = DB::transaction(function () use ($request, $validated, $disk, $path, $file, $fileHash, $hashAlgo, $retentionUntil) {
                $invoice = InvoiceDocument::create([
                    'type' => $validated['type'],
                    'status' => InvoiceDocument::STATUS_RECEIVED,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'source_filename' => $file->getClientOriginalName(),
                    'file_hash' => $fileHash,
                    'hash_algo' => $hashAlgo,
                    'file_size' => $file->getSize() ?? 0,
                    'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                    'retention_until' => $retentionUntil,
                    'metadata' => $validated['metadata'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);

                $this->recordHistory(
                    $invoice,
                    'uploaded',
                    $invoice->status,
                    $request,
                    ['retention_until' => $retentionUntil]
                );

                return $invoice;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return response()->json([
            'document' => $document,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $document = InvoiceDocument::with(['fields', 'statusHistory'])->findOrFail($id);

        return response()->json(['document' => $document]);
    }

    public function download(Request $request, int $id)
    {
        $document = InvoiceDocument::findOrFail($id);

        $this->recordHistory($document, 'downloaded', $document->status, $request);

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->source_filename);
    }

    public function history(Request $request, int $id)
    {
        $document = InvoiceDocument::findOrFail($id);

        return response()->json([
            'history' => $document->statusHistory,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in($this->allowedStatuses())],
        ]);

        $document = InvoiceDocument::findOrFail($id);
        $oldStatus = $document->status;

        if ($oldStatus === $validated['status']) {
            return response()->json(['document' => $document]);
        }

        $document->status = $validated['status'];
        $document->save();

        $this->recordHistory(
            $document,
            'status_updated',
            $document->status,
            $request,
            ['from' => $oldStatus, 'to' => $document->status]
        );

        return response()->json(['document' => $document]);
    }

    public function updateExtraction(Request $request, int $id)
    {
        $validated = $request->validate([
            'raw_text' => ['nullable', 'string'],
            'ocr_json' => ['nullable', 'array'],
            'extracted_fields' => ['nullable', 'array'],
            'field_confidence' => ['nullable', 'array'],
            'normalized_fields' => ['nullable', 'array'],
            'approved_fields' => ['nullable', 'array'],
        ]);

        $document = InvoiceDocument::findOrFail($id);
        $updatedKeys = [];

        if (array_key_exists('raw_text', $validated)) {
            $document->raw_text = $validated['raw_text'];
            $updatedKeys[] = 'raw_text';
        }

        if (array_key_exists('ocr_json', $validated)) {
            $document->ocr_json = $validated['ocr_json'];
            $updatedKeys[] = 'ocr_json';
        }

        if ($document->isDirty()) {
            $document->save();
        }

        $fields = InvoiceField::firstOrNew(['invoice_document_id' => $document->id]);
        $fieldsChanged = false;

        foreach (['extracted_fields', 'field_confidence', 'normalized_fields', 'approved_fields'] as $fieldKey) {
            if (array_key_exists($fieldKey, $validated)) {
                $fields->{$fieldKey} = $validated[$fieldKey];
                $fieldsChanged = true;
                $updatedKeys[] = $fieldKey;
            }
        }

        if (array_key_exists('approved_fields', $validated)) {
            $fields->approved_at = now();
            $fields->approved_by = $request->user()?->id;
        }

        if ($fieldsChanged) {
            $fields->save();
        }

        if (!empty($updatedKeys)) {
            $this->recordHistory(
                $document,
                'extraction_updated',
                $document->status,
                $request,
                ['updated' => $updatedKeys]
            );
        }

        return response()->json([
            'document' => $document->load('fields'),
        ]);
    }

    private function allowedRetentionYears(): array
    {
        $default = (int) config('invoices.retention_years_default', 7);
        $immovable = (int) config('invoices.retention_years_immovable_property', 10);

        return array_values(array_unique([$default, $immovable]));
    }

    private function allowedStatuses(): array
    {
        return [
            InvoiceDocument::STATUS_RECEIVED,
            InvoiceDocument::STATUS_PROCESSING,
            InvoiceDocument::STATUS_APPROVED,
            InvoiceDocument::STATUS_VOID,
            InvoiceDocument::STATUS_ARCHIVED,
            InvoiceDocument::STATUS_CREDITED,
        ];
    }

    private function recordHistory(
        InvoiceDocument $document,
        string $action,
        string $status,
        Request $request,
        array $metadata = []
    ): void {
        $metadata = array_merge($metadata, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        InvoiceStatusHistory::create([
            'invoice_document_id' => $document->id,
            'status' => $status,
            'action' => $action,
            'actor_id' => $request->user()?->id,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
