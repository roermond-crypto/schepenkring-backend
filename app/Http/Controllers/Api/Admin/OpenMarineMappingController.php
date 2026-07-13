<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpenMarineFieldMapping;
use App\Models\OpenMarineFieldMappingVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD for OpenMarineFieldMapping rows — previously read-only. Every
 * mutation snapshots the *entire* mapping table into a new
 * OpenMarineFieldMappingVersion (one version = the whole table's state,
 * not a single row — a single mapping change affects the overall export
 * shape), following the same snapshot-on-change / restore-as-new-version
 * pattern already used by ContractTemplateVersion.
 */
class OpenMarineMappingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $changeNote = $data['change_note'] ?? null;
        unset($data['change_note']);

        $mapping = OpenMarineFieldMapping::create($data);

        $this->snapshot($request, $changeNote);

        return response()->json($mapping, 201);
    }

    public function update(Request $request, OpenMarineFieldMapping $mapping): JsonResponse
    {
        $data = $this->validated($request, $mapping->id);
        $changeNote = $data['change_note'] ?? null;
        unset($data['change_note']);

        $mapping->update($data);

        $this->snapshot($request, $changeNote);

        return response()->json($mapping->fresh());
    }

    public function destroy(Request $request, OpenMarineFieldMapping $mapping): JsonResponse
    {
        $mapping->delete();

        $this->snapshot($request, "Removed mapping for '{$mapping->schepenkring_field}'");

        return response()->json(['message' => 'Mapping deleted']);
    }

    public function versions(): JsonResponse
    {
        $versions = OpenMarineFieldMappingVersion::with('createdBy:id,name')
            ->orderByDesc('version')
            ->get(['id', 'version', 'change_note', 'created_by_id', 'created_at']);

        return response()->json(['data' => $versions]);
    }

    public function showVersion(OpenMarineFieldMappingVersion $version): JsonResponse
    {
        return response()->json($version);
    }

    public function restoreVersion(Request $request, OpenMarineFieldMappingVersion $version): JsonResponse
    {
        OpenMarineFieldMapping::query()->delete();

        foreach ($version->mappings_snapshot as $row) {
            OpenMarineFieldMapping::create([
                'schepenkring_field' => $row['schepenkring_field'] ?? '',
                'openmarine_xml_path' => $row['openmarine_xml_path'],
                'default_value' => $row['default_value'] ?? null,
                'group_label' => $row['group_label'] ?? null,
                'is_required' => $row['is_required'] ?? false,
                'notes' => $row['notes'] ?? null,
            ]);
        }

        $newVersion = $this->snapshot($request, "Restored from version {$version->version}");

        return response()->json($newVersion);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:openmarine_field_mappings,openmarine_xml_path'
            . ($ignoreId ? ",{$ignoreId}" : '');

        return $request->validate([
            'schepenkring_field' => 'present|string|max:150',
            'openmarine_xml_path' => "required|string|max:200|{$uniqueRule}",
            'default_value' => 'nullable|string|max:200',
            'group_label' => 'nullable|string|max:80',
            'is_required' => 'boolean',
            'notes' => 'nullable|string',
            'change_note' => 'nullable|string|max:500',
        ]);
    }

    private function snapshot(Request $request, ?string $changeNote): OpenMarineFieldMappingVersion
    {
        $nextVersion = (OpenMarineFieldMappingVersion::max('version') ?? 0) + 1;

        return OpenMarineFieldMappingVersion::create([
            'version' => $nextVersion,
            'mappings_snapshot' => OpenMarineFieldMapping::orderBy('id')->get()->toArray(),
            'change_note' => $changeNote,
            'created_by_id' => $request->user()?->id,
            'created_at' => now(),
        ]);
    }
}
