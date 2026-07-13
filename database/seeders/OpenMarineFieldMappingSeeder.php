<?php

namespace Database\Seeders;

use App\Models\OpenMarineFieldMapping;
use Illuminate\Database\Seeder;

/**
 * The live source of truth for OpenMarineService::buildXml() — the XML
 * generator resolves every field from these rows at runtime (see
 * resolveGroupNode()/resolveField()), not from a hardcoded PHP sequence.
 * Editing a row here (via the mapping editor) genuinely changes what gets
 * exported. This seeder exists to (re)populate the table from a known-good
 * starting state, not as documentation of separate hardcoded logic.
 *
 * Path conventions the resolver understands:
 *  - 'boat.manufacturer'            → element at boat > manufacturer
 *  - 'boat.images.image[@order]'    → attribute "order" on the current
 *                                      array_source-repeated node
 *  - 'boat.descriptions.description[lang=nl]' → a repeated element with a
 *                                      fixed attribute (lang="nl") and CDATA
 *                                      content — one row per language
 *  - schepenkring_field = ''        → not resolved from the yacht at all;
 *                                      default_value is used as a constant
 *  - schepenkring_field starting with 'images[].' → resolved per-image from
 *                                      $yacht->images (same convention
 *                                      resolveMappedValue() already used for
 *                                      the inspection view)
 */
class OpenMarineFieldMappingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->mappings() as $mapping) {
            OpenMarineFieldMapping::updateOrCreate(
                [
                    'schepenkring_field' => $mapping['schepenkring_field'],
                    'openmarine_xml_path' => $mapping['openmarine_xml_path'],
                ],
                $mapping,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mappings(): array
    {
        return [
            ['schepenkring_field' => 'id', 'openmarine_xml_path' => 'boat.id', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'vessel_id', 'openmarine_xml_path' => 'boat.reference', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'manufacturer', 'openmarine_xml_path' => 'boat.manufacturer', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'model', 'openmarine_xml_path' => 'boat.model', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'year', 'openmarine_xml_path' => 'boat.year', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'boat_type', 'openmarine_xml_path' => 'boat.boat_type', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'boat_category', 'openmarine_xml_path' => 'boat.boat_category', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'new_or_used', 'openmarine_xml_path' => 'boat.new_or_used', 'default_value' => 'used', 'group_label' => 'Identity', 'is_required' => false, 'notes' => "Defaults to 'used' when empty."],
            ['schepenkring_field' => 'boat_name', 'openmarine_xml_path' => 'boat.name', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'status', 'openmarine_xml_path' => 'boat.status', 'default_value' => null, 'group_label' => 'Identity', 'is_required' => false, 'notes' => 'Gates exportability — only approved/published/active pass validate().'],

            ['schepenkring_field' => 'price', 'openmarine_xml_path' => 'boat.price.amount', 'default_value' => null, 'group_label' => 'Price', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => '', 'openmarine_xml_path' => 'boat.price.currency', 'default_value' => 'EUR', 'group_label' => 'Price', 'is_required' => false, 'notes' => 'Constant — not read from the yacht.'],
            ['schepenkring_field' => '', 'openmarine_xml_path' => 'boat.price.vat', 'default_value' => 'excluded', 'group_label' => 'Price', 'is_required' => false, 'notes' => 'Constant — not read from the yacht.'],

            ['schepenkring_field' => 'loa', 'openmarine_xml_path' => 'boat.dimensions.loa', 'default_value' => null, 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'beam', 'openmarine_xml_path' => 'boat.dimensions.beam', 'default_value' => null, 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'draft', 'openmarine_xml_path' => 'boat.dimensions.draft', 'default_value' => null, 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'displacement', 'openmarine_xml_path' => 'boat.dimensions.displacement', 'default_value' => null, 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'engine_manufacturer', 'openmarine_xml_path' => 'boat.engine.manufacturer', 'default_value' => null, 'group_label' => 'Engine', 'is_required' => false, 'notes' => 'Engine block only renders if engine_manufacturer or horse_power is set.'],
            ['schepenkring_field' => 'engine_model', 'openmarine_xml_path' => 'boat.engine.model', 'default_value' => null, 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'horse_power', 'openmarine_xml_path' => 'boat.engine.horsepower', 'default_value' => null, 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'hours', 'openmarine_xml_path' => 'boat.engine.hours', 'default_value' => null, 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'fuel', 'openmarine_xml_path' => 'boat.engine.fuel', 'default_value' => null, 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'engine_year', 'openmarine_xml_path' => 'boat.engine.year', 'default_value' => null, 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'short_description_nl', 'openmarine_xml_path' => 'boat.descriptions.description[lang=nl]', 'default_value' => null, 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => 'validate() warns (not errors) if neither NL nor EN description is set.'],
            ['schepenkring_field' => 'short_description_en', 'openmarine_xml_path' => 'boat.descriptions.description[lang=en]', 'default_value' => null, 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'short_description_de', 'openmarine_xml_path' => 'boat.descriptions.description[lang=de]', 'default_value' => null, 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'short_description_fr', 'openmarine_xml_path' => 'boat.descriptions.description[lang=fr]', 'default_value' => null, 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'location_city', 'openmarine_xml_path' => 'boat.location.city', 'default_value' => null, 'group_label' => 'Location', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'location_country', 'openmarine_xml_path' => 'boat.location.country', 'default_value' => 'NL', 'group_label' => 'Location', 'is_required' => false, 'notes' => "Falls back to 'NL' when unset. Column added 2026-07-12 — governed autocomplete field, editable in the wizard."],
            ['schepenkring_field' => 'location_lat', 'openmarine_xml_path' => 'boat.location.lat', 'default_value' => null, 'group_label' => 'Location', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'location_lng', 'openmarine_xml_path' => 'boat.location.lng', 'default_value' => null, 'group_label' => 'Location', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'images[].url', 'openmarine_xml_path' => 'boat.images.image.url', 'default_value' => null, 'group_label' => 'Images', 'is_required' => true, 'notes' => 'validate() requires at least 1 image, warns below 3.'],
            ['schepenkring_field' => 'images[].caption', 'openmarine_xml_path' => 'boat.images.image.caption', 'default_value' => null, 'group_label' => 'Images', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'images[].sort_order', 'openmarine_xml_path' => 'boat.images.image[@order]', 'default_value' => null, 'group_label' => 'Images', 'is_required' => false, 'notes' => null],
        ];
    }
}
