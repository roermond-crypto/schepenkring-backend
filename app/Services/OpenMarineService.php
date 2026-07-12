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
    private const EXPORTABLE_STATUSES = ['approved', 'published', 'active'];

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
        if (str_starts_with($field, '(')) {
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
     * Build the OpenMarine 2.0 XML string.
     */
    private function buildXml(Yacht $yacht): string
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
            // optimized_url resolves to a fully-qualified public URL (falls back
            // to full_url internally) — the raw `url` column is a storage-relative
            // path and `alt_text`/`order` never existed as YachtImage columns
            // (the real columns are `caption` and `sort_order`), so this was
            // silently exporting broken image URLs and empty captions/order.
            $this->addSafe($imgNode, 'url',     $image->optimized_url ?? '');
            $this->addSafe($imgNode, 'caption', $image->caption ?? '');
            $imgNode->addAttribute('order', (string) ($image->sort_order ?? 0));
        }

        // Format
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
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
}
