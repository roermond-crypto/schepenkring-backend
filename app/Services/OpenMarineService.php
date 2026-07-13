<?php

namespace App\Services;

use App\Models\OpenMarineFieldMapping;
use App\Models\Yacht;
use SimpleXMLElement;

class OpenMarineService
{
    /**
     * Statuses that are safe to export.
     * Draft / pending boats are never included.
     */
    public const EXPORTABLE_STATUSES = ['approved', 'published', 'active'];

    /**
     * Required OpenMarine 2.0 fields mapped to Yacht attributes.
     * key = OpenMarine field name, value = yacht attribute path
     */
    private const REQUIRED_FIELDS = [
        'manufacturer' => 'manufacturer',
        'model'        => 'model',
        'year'         => 'year',
        'price'        => 'price',
        'boat_type'    => 'boat_type',
    ];

    /**
     * Generate an OpenMarine 2.0 XML string for a single yacht.
     *
     * @return array{xml: string, errors: string[], warnings: string[], valid: bool}
     */
    public function generate(Yacht $yacht): array
    {
        [$errors, $warnings] = $this->validate($yacht);

        $xml = $this->buildXml($yacht);

        return [
            'xml'      => $xml,
            'errors'   => $errors,
            'warnings' => $warnings,
            'valid'    => empty($errors),
        ];
    }

    /**
     * Validate a yacht against OpenMarine 2.0 requirements.
     *
     * @return array{0: string[], 1: string[]}  [errors, warnings]
     */
    public function validate(Yacht $yacht): array
    {
        $errors   = [];
        $warnings = [];

        // Test yachts (from the Integration Center's Test Yacht Generator)
        // can still be previewed/generated for mapping tests, but must
        // never pass validation for a real publish.
        if (! empty($yacht->is_test)) {
            $errors[] = 'Test yachts are excluded from publishing.';
        }

        // Status gate
        if (! in_array($yacht->status, self::EXPORTABLE_STATUSES, true)) {
            $errors[] = "Boat status '{$yacht->status}' is not exportable. Must be approved or published.";
        }

        // Required fields
        foreach (self::REQUIRED_FIELDS as $label => $attr) {
            $value = $yacht->{$attr} ?? null;
            if ($value === null || $value === '') {
                $errors[] = "Required field missing: {$label}";
            }
        }

        // Price sanity
        if (isset($yacht->price) && $yacht->price <= 0) {
            $errors[] = 'Price must be greater than 0.';
        }

        // Images
        $imageCount = $yacht->images?->count() ?? 0;
        if ($imageCount === 0) {
            $errors[] = 'At least one image is required for export.';
        } elseif ($imageCount < 3) {
            $warnings[] = "Only {$imageCount} image(s) uploaded. Recommend at least 3.";
        }

        // Description
        $hasDesc = ! empty($yacht->short_description_nl)
            || ! empty($yacht->short_description_en);
        if (! $hasDesc) {
            $warnings[] = 'No description in NL or EN. Description is strongly recommended.';
        }

        // Location
        if (empty($yacht->location_id) && empty($yacht->location_city)) {
            $warnings[] = 'No location set. Location improves visibility on platforms.';
        }

        return [$errors, $warnings];
    }

    /**
     * For the Integration Center's debugging view: every seeded field
     * mapping alongside this yacht's current resolved value, so an admin
     * can see field-by-field exactly why an export is failing or thin
     * without reading buildXml() source.
     *
     * @return array<int, array{schepenkring_field: string, openmarine_xml_path: string, group_label: ?string, is_required: bool, notes: ?string, current_value: mixed, populated: bool}>
     */
    public function inspectMapping(Yacht $yacht): array
    {
        return OpenMarineFieldMapping::query()
            ->orderBy('group_label')
            ->orderBy('id')
            ->get()
            ->map(function (OpenMarineFieldMapping $mapping) use ($yacht) {
                [$value, $populated] = $this->resolveMappedValue($yacht, $mapping->schepenkring_field);

                return [
                    'schepenkring_field' => $mapping->schepenkring_field,
                    'openmarine_xml_path' => $mapping->openmarine_xml_path,
                    'group_label' => $mapping->group_label,
                    'is_required' => $mapping->is_required,
                    'notes' => $mapping->notes,
                    'current_value' => $value,
                    'populated' => $populated,
                ];
            })
            ->all();
    }

    /**
     * @return array{0: mixed, 1: bool}
     */
    private function resolveMappedValue(Yacht $yacht, string $field): array
    {
        if ($field === '' || str_starts_with($field, '(')) {
            return [null, true];
        }

        if (str_starts_with($field, 'images[].')) {
            $column = substr($field, strlen('images[].'));
            $images = $yacht->images ?? collect();
            $total = $images->count();
            $populatedCount = $images->filter(fn ($image) => filled($image->{$column} ?? null))->count();

            return ["{$populatedCount}/{$total} images", $total > 0 && $populatedCount === $total];
        }

        $value = $yacht->{$field} ?? null;

        return [$value, $value !== null && $value !== ''];
    }

    /**
     * Groups render as a child element of <boat> (e.g. <price>, <engine>),
     * except Identity, whose fields attach directly to <boat>. Order here is
     * the actual output order and matches the mapping table's original
     * hardcoded sequence.
     */
    private const GROUP_ORDER = ['Identity', 'Price', 'Dimensions', 'Engine', 'Descriptions', 'Location', 'Images'];

    private const GROUP_NODE_NAMES = [
        'Price' => 'price',
        'Dimensions' => 'dimensions',
        'Engine' => 'engine',
        'Location' => 'location',
    ];

    /**
     * The <engine> node is only emitted when at least one of these specific
     * yacht attributes is non-empty (matches the original condition exactly
     * — NOT "any engine field resolved," which would also fire for e.g. a
     * lone engine_year with no manufacturer/horsepower, something the
     * original hardcoded implementation never did).
     */
    private const ENGINE_TRIGGER_FIELDS = ['engine_manufacturer', 'horse_power'];

    /**
     * Build the OpenMarine 2.0 XML string from the OpenMarineFieldMapping
     * table — this is the actual export logic; editing a mapping row (via
     * the mapping editor) genuinely changes what gets generated here.
     */
    private function buildXml(Yacht $yacht): string
    {
        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><openmarine version="2.0"/>'
        );
        $boatNode = $xml->addChild('boat');

        $mappingsByGroup = OpenMarineFieldMapping::orderBy('id')
            ->get()
            ->groupBy(fn (OpenMarineFieldMapping $m) => $m->group_label ?? 'Identity');

        foreach (self::GROUP_ORDER as $groupLabel) {
            $rows = $mappingsByGroup->get($groupLabel);
            if (! $rows || $rows->isEmpty()) {
                continue;
            }

            match ($groupLabel) {
                'Identity' => $this->applyScalarFields($boatNode, $rows, $yacht),
                'Descriptions' => $this->buildDescriptionsNode($boatNode, $rows, $yacht),
                'Images' => $this->buildImagesNode($boatNode, $rows, $yacht),
                'Engine' => $this->buildEngineNode($boatNode, $rows, $yacht),
                'Location' => $this->buildLocationNode($boatNode, $rows, $yacht),
                default => $this->buildScalarGroupNode($boatNode, $groupLabel, $rows, $yacht),
            };
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * Price / Dimensions — a wrapper element (always emitted, even empty)
     * containing one child element per mapping row, resolved directly off
     * the yacht. No group-level conditional logic here — Engine and
     * Location each have their own quirk the original hardcoded
     * implementation applied, handled by their dedicated methods instead.
     */
    private function buildScalarGroupNode(SimpleXMLElement $boatNode, string $groupLabel, $rows, Yacht $yacht): void
    {
        $groupNode = $boatNode->addChild(self::GROUP_NODE_NAMES[$groupLabel]);
        foreach ($rows as $row) {
            $value = $this->resolveScalar($row, $yacht);
            $this->addSafe($groupNode, $this->lastPathSegment($row->openmarine_xml_path), (string) ($value ?? ''));
        }
    }

    /**
     * The <engine> node is only emitted when engine_manufacturer or
     * horse_power is non-empty (matches the original condition exactly).
     */
    private function buildEngineNode(SimpleXMLElement $boatNode, $rows, Yacht $yacht): void
    {
        $triggered = false;
        foreach (self::ENGINE_TRIGGER_FIELDS as $field) {
            if (! empty($yacht->{$field})) {
                $triggered = true;
                break;
            }
        }

        if (! $triggered) {
            return;
        }

        $engineNode = $boatNode->addChild('engine');
        foreach ($rows as $row) {
            $value = $this->resolveScalar($row, $yacht);
            $this->addSafe($engineNode, $this->lastPathSegment($row->openmarine_xml_path), (string) ($value ?? ''));
        }
    }

    /**
     * <location> is always emitted (city/country resolve normally), but
     * lat/lng are a pair — both are only added when location_lat is
     * non-empty, matching the original condition exactly (a lone lng with
     * no lat was never emitted).
     */
    private function buildLocationNode(SimpleXMLElement $boatNode, $rows, Yacht $yacht): void
    {
        $locationNode = $boatNode->addChild('location');

        foreach ($rows as $row) {
            $elementName = $this->lastPathSegment($row->openmarine_xml_path);

            if (in_array($elementName, ['lat', 'lng'], true)) {
                continue; // handled separately below, as a pair
            }

            $value = $this->resolveScalar($row, $yacht);
            $this->addSafe($locationNode, $elementName, (string) ($value ?? ''));
        }

        if (! empty($yacht->location_lat)) {
            $latRow = $rows->first(fn ($r) => $this->lastPathSegment($r->openmarine_xml_path) === 'lat');
            $lngRow = $rows->first(fn ($r) => $this->lastPathSegment($r->openmarine_xml_path) === 'lng');

            if ($latRow) {
                $this->addSafe($locationNode, 'lat', (string) ($this->resolveScalar($latRow, $yacht) ?? ''));
            }
            if ($lngRow) {
                $this->addSafe($locationNode, 'lng', (string) ($this->resolveScalar($lngRow, $yacht) ?? ''));
            }
        }
    }

    /**
     * Identity fields attach directly to <boat>, no wrapper element.
     */
    private function applyScalarFields(SimpleXMLElement $boatNode, $rows, Yacht $yacht): void
    {
        foreach ($rows as $row) {
            $elementName = $this->lastPathSegment($row->openmarine_xml_path);
            $value = $this->resolveScalar($row, $yacht);
            $this->addSafe($boatNode, $elementName, (string) ($value ?? ''));
        }
    }

    /**
     * One <description lang="X"> CDATA node per mapping row — the row's
     * openmarine_xml_path ends in "[lang=XX]" to declare which language it
     * represents (one row per language, not a loop over a single row).
     */
    private function buildDescriptionsNode(SimpleXMLElement $boatNode, $rows, Yacht $yacht): void
    {
        $descNode = $boatNode->addChild('descriptions');

        foreach ($rows as $row) {
            if (! preg_match('/\[lang=([a-z]{2})\]$/', $row->openmarine_xml_path, $m)) {
                continue;
            }

            $text = $this->resolveScalar($row, $yacht);
            if ($text === null || $text === '') {
                continue;
            }

            $d = $descNode->addChild('description');
            $d->addAttribute('lang', $m[1]);
            $node = dom_import_simplexml($d);
            $node->appendChild($node->ownerDocument->createCDATASection($text));
        }
    }

    /**
     * One <image> node per $yacht->images row. A mapping row whose path ends
     * in "[@attrname]" becomes an XML attribute on the <image> node instead
     * of a child element (used for the "order" attribute).
     */
    private function buildImagesNode(SimpleXMLElement $boatNode, $rows, Yacht $yacht): void
    {
        $imagesNode = $boatNode->addChild('images');

        foreach (($yacht->images ?? []) as $image) {
            $imgNode = $imagesNode->addChild('image');

            foreach ($rows as $row) {
                if (! str_starts_with($row->schepenkring_field, 'images[].')) {
                    continue;
                }

                $column = substr($row->schepenkring_field, strlen('images[].'));
                $value = $image->{$column} ?? null;

                if (preg_match('/\[@([a-zA-Z_]+)\]$/', $row->openmarine_xml_path, $m)) {
                    $imgNode->addAttribute($m[1], (string) ($value ?? 0));
                } else {
                    $this->addSafe($imgNode, $this->lastPathSegment($row->openmarine_xml_path), (string) ($value ?? ''));
                }
            }
        }
    }

    /**
     * Resolves one mapping row's value: '' schepenkring_field means a
     * constant (default_value used as-is, never touching the yacht);
     * otherwise the yacht attribute is read, falling back to default_value
     * only when the resolved value is NULL — matching the original
     * hardcoded implementation's `?? $default` semantics exactly. An empty
     * string is a real (if unlikely) value and is deliberately NOT treated
     * as "missing" here, same as the original — it just won't render since
     * addSafe() skips empty strings regardless of how they got that way.
     */
    private function resolveScalar(OpenMarineFieldMapping $row, Yacht $yacht): ?string
    {
        if ($row->schepenkring_field === '' || $row->schepenkring_field === null) {
            return $row->default_value;
        }

        $value = $yacht->{$row->schepenkring_field} ?? null;
        if ($value === null) {
            return $row->default_value;
        }

        return (string) $value;
    }

    private function lastPathSegment(string $path): string
    {
        $withoutBracket = preg_replace('/\[.*\]$/', '', $path);
        $segments = explode('.', $withoutBracket);

        return end($segments);
    }

    /**
     * Add a child node only when the value is non-empty.
     */
    private function addSafe(SimpleXMLElement $parent, string $name, string $value): void
    {
        if ($value !== '') {
            $parent->addChild($name, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }
    }

    /**
     * The exact original hardcoded implementation buildXml() replaced,
     * preserved verbatim (only renamed) so `openmarine:verify-mapping-parity`
     * can diff its output against the new data-driven buildXml() before
     * anyone trusts the rewrite in production. This environment has no
     * working database driver to run that diff during development, so it
     * was verified only by careful manual line-by-line comparison — treat
     * that as a lower confidence level than an actual executed diff, and
     * run the parity command for real before relying on this.
     */
    public function buildXmlLegacy(Yacht $yacht): string
    {
        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><openmarine version="2.0"/>'
        );

        $boatNode = $xml->addChild('boat');

        // Identity
        $this->addSafe($boatNode, 'id',           (string) $yacht->id);
        $this->addSafe($boatNode, 'reference',     $yacht->vessel_id ?? '');
        $this->addSafe($boatNode, 'manufacturer',  $yacht->manufacturer ?? '');
        $this->addSafe($boatNode, 'model',         $yacht->model ?? '');
        $this->addSafe($boatNode, 'year',          (string) ($yacht->year ?? ''));
        $this->addSafe($boatNode, 'boat_type',     $yacht->boat_type ?? '');
        $this->addSafe($boatNode, 'boat_category', $yacht->boat_category ?? '');
        $this->addSafe($boatNode, 'new_or_used',   $yacht->new_or_used ?? 'used');
        $this->addSafe($boatNode, 'name',          $yacht->boat_name ?? '');
        $this->addSafe($boatNode, 'status',        $yacht->status ?? '');

        // Price
        $priceNode = $boatNode->addChild('price');
        $this->addSafe($priceNode, 'amount',   (string) ($yacht->price ?? ''));
        $this->addSafe($priceNode, 'currency', 'EUR');
        $this->addSafe($priceNode, 'vat',      'excluded');

        // Dimensions
        $dimNode = $boatNode->addChild('dimensions');
        $this->addSafe($dimNode, 'loa',          (string) ($yacht->loa ?? ''));
        $this->addSafe($dimNode, 'beam',         (string) ($yacht->beam ?? ''));
        $this->addSafe($dimNode, 'draft',        (string) ($yacht->draft ?? ''));
        $this->addSafe($dimNode, 'displacement', (string) ($yacht->displacement ?? ''));

        // Engine
        if (! empty($yacht->engine_manufacturer) || ! empty($yacht->horse_power)) {
            $engNode = $boatNode->addChild('engine');
            $this->addSafe($engNode, 'manufacturer', $yacht->engine_manufacturer ?? '');
            $this->addSafe($engNode, 'model',        $yacht->engine_model ?? '');
            $this->addSafe($engNode, 'horsepower',   (string) ($yacht->horse_power ?? ''));
            $this->addSafe($engNode, 'hours',        (string) ($yacht->hours ?? ''));
            $this->addSafe($engNode, 'fuel',         $yacht->fuel ?? '');
            $this->addSafe($engNode, 'year',         (string) ($yacht->engine_year ?? ''));
        }

        // Descriptions (multilingual)
        $descNode = $boatNode->addChild('descriptions');
        foreach (['nl', 'en', 'de', 'fr'] as $lang) {
            $text = $yacht->{"short_description_{$lang}"} ?? '';
            if ($text !== '') {
                $d = $descNode->addChild('description');
                $d->addAttribute('lang', $lang);
                $node = dom_import_simplexml($d);
                $node->appendChild($node->ownerDocument->createCDATASection($text));
            }
        }

        // Location
        $locNode = $boatNode->addChild('location');
        $this->addSafe($locNode, 'city',    $yacht->location_city ?? '');
        $this->addSafe($locNode, 'country', $yacht->location_country ?? 'NL');
        if (! empty($yacht->location_lat)) {
            $this->addSafe($locNode, 'lat', (string) $yacht->location_lat);
            $this->addSafe($locNode, 'lng', (string) $yacht->location_lng);
        }

        // Images
        $imagesNode = $boatNode->addChild('images');
        foreach (($yacht->images ?? []) as $image) {
            $imgNode = $imagesNode->addChild('image');
            $this->addSafe($imgNode, 'url',     $image->optimized_url ?? '');
            $this->addSafe($imgNode, 'caption', $image->caption ?? '');
            $imgNode->addAttribute('order', (string) ($image->sort_order ?? 0));
        }

        // Format
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
