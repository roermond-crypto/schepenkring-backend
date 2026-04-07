<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Faq;
use App\Models\FaqKnowledgeDocument;
use App\Models\FaqKnowledgeItem;
use App\Models\KnowledgeEntity;
use App\Models\KnowledgeIngestionRun;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('staff can upload a knowledge document and generate pending faq review items', function () {
    Storage::fake('local');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', 'test-openai');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'items' => [
                            [
                                'question' => 'What documents are needed to sell a boat?',
                                'answer' => 'The sales package requires the registration papers, proof of identity, and the signed sale agreement.',
                            ],
                            [
                                'question' => 'Do sellers need proof of identity?',
                                'answer' => 'Yes. A valid proof of identity is required before the sale file can be completed.',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'category' => 'Sales',
            'source_type' => 'pdf_document',
            'file' => UploadedFile::fake()->createWithContent(
                'knowledge.txt',
                'Selling a boat requires registration papers, proof of identity, and a signed sale agreement.'
            ),
        ]);

    $response->assertCreated()
        ->assertJsonPath('document.status', 'pending_review')
        ->assertJsonPath('document.generated_qna_count', 2)
        ->assertJsonPath('items.0.status', 'pending')
        ->assertJsonPath('items.0.category', 'Sales');

    $document = FaqKnowledgeDocument::query()->first();
    $item = FaqKnowledgeItem::query()->first();
    $documentEntity = KnowledgeEntity::query()
        ->where('type', 'document')
        ->where('source_table', 'faq_knowledge_documents')
        ->where('source_id', $document?->id)
        ->first();
    $run = KnowledgeIngestionRun::query()->first();

    expect($document)->not->toBeNull();
    expect($document->source_type)->toBe('pdf_document');
    expect($item)->not->toBeNull();
    expect($item->status)->toBe('pending');
    expect($item->approved_faq_id)->toBeNull();
    expect($documentEntity)->not->toBeNull();
    expect($documentEntity->title)->toBe($document->file_name);
    expect($run)->not->toBeNull();
    expect($run->status)->toBe('completed');
    expect($run->documents_count)->toBe(1);
    expect($run->chunks_count)->toBe(1);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/vectors/upsert'));
});

test('staff can upload an xlsx knowledge document and extract spreadsheet text', function () {
    Storage::fake('local');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', 'test-openai');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'items' => [[
                            'question' => 'What is Nautic Secure?',
                            'answer' => 'It is a secure platform for structured boat sales.',
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'category' => 'Platform',
            'file' => createKnowledgeWorkbookUpload([
                ['Platform', 'Section', 'Question', 'Answer'],
                ['Nautic Secure', 'Identity', 'What is Nautic Secure?', 'It is a secure platform for structured boat sales.'],
            ]),
        ]);

    $response->assertCreated()
        ->assertJsonPath('document.status', 'pending_review')
        ->assertJsonPath('document.generated_qna_count', 1)
        ->assertJsonPath('items.0.status', 'pending')
        ->assertJsonPath('items.0.category', 'Platform');

    $document = FaqKnowledgeDocument::query()->first();

    expect($document)->not->toBeNull();
    expect($document->extension)->toBe('xlsx');
    expect($document->extracted_text)->toContain('What is Nautic Secure?');
    expect($document->extracted_text)->toContain('It is a secure platform for structured boat sales.');
});

test('staff can upload a knowledge document when auxiliary knowledge tables are missing', function () {
    Storage::fake('local');

    Schema::dropIfExists('knowledge_ingestion_runs');
    Schema::dropIfExists('knowledge_relationships');
    Schema::dropIfExists('knowledge_entities');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', 'test-openai');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'items' => [[
                            'question' => 'Which documents are required?',
                            'answer' => 'Registration papers and identification are required.',
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'file' => UploadedFile::fake()->createWithContent(
                'knowledge.txt',
                'Registration papers and identification are required before processing the sale.'
            ),
        ]);

    $response->assertCreated()
        ->assertJsonPath('document.status', 'pending_review')
        ->assertJsonPath('document.generated_qna_count', 1);

    expect(FaqKnowledgeDocument::query()->count())->toBe(1);
    expect(FaqKnowledgeItem::query()->count())->toBe(1);
});

test('upload returns a validation error when OpenAI FAQ generation is unavailable', function () {
    Storage::fake('local');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', null);

    Sanctum::actingAs($employee);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'file' => UploadedFile::fake()->createWithContent(
                'knowledge.txt',
                'Registration papers and identification are required before processing the sale.'
            ),
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'OPENAI_API_KEY is not configured.');

    expect(FaqKnowledgeDocument::query()->first()?->status)->toBe('failed');
});

test('approving a generated knowledge item creates a faq and upserts it to pinecone', function () {
    Storage::fake('local');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', 'test-openai');
    config()->set('services.pinecone.key', 'test-pinecone');
    config()->set('services.pinecone.host', 'https://pinecone.test');
    config()->set('services.pinecone.namespace', 'copilot');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'items' => [[
                            'question' => 'Can a client change booking after payment?',
                            'answer' => 'Clients can request a booking change after payment, but approval depends on timing and the configured policy.',
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [[
                'embedding' => [0.1, 0.2, 0.3],
            ]],
        ], 200),
        'https://pinecone.test/vectors/upsert' => Http::response([
            'upsertedCount' => 1,
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $upload = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'category' => 'Bookings',
            'department' => 'Sales',
            'visibility' => 'internal',
            'file' => UploadedFile::fake()->createWithContent(
                'booking-policy.txt',
                'Clients can request a booking change after payment. Approval depends on timing and company policy.'
            ),
        ]);

    $upload->assertCreated();

    $item = FaqKnowledgeItem::query()->first();
    expect($item)->not->toBeNull();

    $review = $this->patchJson("/api/faqs/knowledge/items/{$item->id}", [
        'status' => 'approved',
        'question' => 'Can a client change booking after payment?',
        'answer' => 'Clients can request a booking change after payment, but approval depends on timing and the configured company policy.',
    ]);

    $review->assertOk()
        ->assertJsonPath('item.status', 'approved')
        ->assertJsonPath('item.approved_faq_id', 1);

    $faq = Faq::query()->first();
    $faqEntity = KnowledgeEntity::query()
        ->where('type', 'faq')
        ->where('source_table', 'faqs')
        ->where('source_id', $faq?->id)
        ->first();

    expect($faq)->not->toBeNull();
    expect($faq->question)->toBe('Can a client change booking after payment?');
    expect($faq->source_type)->toBe('text_document');
    expect($item->fresh()->approved_faq_id)->toBe($faq->id);
    expect($faqEntity)->not->toBeNull();
    expect(data_get($faqEntity->metadata, 'source_type'))->toBe('text_document');

    Http::assertSent(fn ($request) => $request->url() === 'https://pinecone.test/vectors/upsert');
});

function createKnowledgeWorkbookUpload(array $rows): UploadedFile
{
    $basePath = tempnam(sys_get_temp_dir(), 'faq-xlsx-');
    if ($basePath === false) {
        throw new \RuntimeException('Unable to allocate a temporary workbook path.');
    }

    @unlink($basePath);

    $path = $basePath.'.xlsx';
    $sharedStrings = [];
    $sharedStringIndexes = [];
    $sheetRows = [];

    foreach ($rows as $rowIndex => $row) {
        $cells = [];

        foreach (array_values($row) as $columnIndex => $value) {
            $value = (string) $value;

            if (! array_key_exists($value, $sharedStringIndexes)) {
                $sharedStringIndexes[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            $cells[] = sprintf(
                '<c r="%s" t="s"><v>%d</v></c>',
                spreadsheetCellReference($columnIndex + 1, $rowIndex + 1),
                $sharedStringIndexes[$value]
            );
        }

        $sheetRows[] = sprintf('<row r="%d">%s</row>', $rowIndex + 1, implode('', $cells));
    }

    $zip = new \ZipArchive;
    if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Unable to create a temporary workbook.');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        .'</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        .'</Relationships>');

    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">'
        .implode('', array_map(
            static fn (string $value): string => '<si><t>'.xmlEscape($value).'</t></si>',
            $sharedStrings
        ))
        .'</sst>');

    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
        .'</worksheet>');

    $zip->close();

    register_shutdown_function(static fn () => @unlink($path));

    return new UploadedFile(
        $path,
        'knowledge.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}

function spreadsheetCellReference(int $columnIndex, int $rowIndex): string
{
    $column = '';

    while ($columnIndex > 0) {
        $columnIndex--;
        $column = chr(65 + ($columnIndex % 26)).$column;
        $columnIndex = intdiv($columnIndex, 26);
    }

    return $column.$rowIndex;
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
