<?php

namespace Tests\Feature;

use App\Models\InvoiceDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_invoice_document_and_history(): void
    {
        Storage::fake('local');
        config(['invoices.storage_disk' => 'local']);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/invoices/documents', [
            'type' => 'incoming',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $documentId = $response->json('document.id');

        $this->assertDatabaseHas('invoice_documents', [
            'id' => $documentId,
            'type' => 'incoming',
            'status' => 'received',
        ]);

        $document = InvoiceDocument::findOrFail($documentId);
        Storage::disk('local')->assertExists($document->storage_path);

        $this->assertDatabaseHas('invoice_status_histories', [
            'invoice_document_id' => $documentId,
            'action' => 'uploaded',
            'status' => 'received',
        ]);
    }

    public function test_it_prevents_immutable_field_updates(): void
    {
        Storage::fake('local');
        config(['invoices.storage_disk' => 'local']);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/invoices/documents', [
            'type' => 'outgoing',
            'file' => $file,
        ]);

        $document = InvoiceDocument::findOrFail($response->json('document.id'));
        $document->storage_path = 'tampered/path.pdf';

        $this->expectException(RuntimeException::class);
        $document->save();
    }

    public function test_it_updates_status_and_logs_history(): void
    {
        Storage::fake('local');
        config(['invoices.storage_disk' => 'local']);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/invoices/documents', [
            'type' => 'incoming',
            'file' => $file,
        ]);

        $documentId = $response->json('document.id');

        $update = $this->actingAs($user, 'sanctum')->patchJson("/api/invoices/documents/{$documentId}/status", [
            'status' => 'archived',
        ]);

        $update->assertOk();

        $this->assertDatabaseHas('invoice_documents', [
            'id' => $documentId,
            'status' => 'archived',
        ]);

        $this->assertDatabaseHas('invoice_status_histories', [
            'invoice_document_id' => $documentId,
            'action' => 'status_updated',
            'status' => 'archived',
        ]);
    }
}
