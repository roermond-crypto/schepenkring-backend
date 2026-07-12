<?php

namespace Database\Seeders;

use App\Models\OpenMarineFieldMapping;
use Illuminate\Database\Seeder;

/**
 * Mirrors the actual field mapping hardcoded in OpenMarineService::buildXml()
 * — kept as data so /admin/openmarine can show, for a given yacht, exactly
 * which Schepenkring field feeds which OpenMarine XML element, and whether
 * it's populated. If buildXml() changes, update this seeder to match (there
 * is deliberately no dynamic runtime binding between the two yet — see the
 * commit message for why a live refactor of buildXml() wasn't attempted in
 * this pass).
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
            ['schepenkring_field' => 'id', 'openmarine_xml_path' => 'boat.id', 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'vessel_id', 'openmarine_xml_path' => 'boat.reference', 'group_label' => 'Identity', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'manufacturer', 'openmarine_xml_path' => 'boat.manufacturer', 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'model', 'openmarine_xml_path' => 'boat.model', 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'year', 'openmarine_xml_path' => 'boat.year', 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'boat_type', 'openmarine_xml_path' => 'boat.boat_type', 'group_label' => 'Identity', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => 'boat_category', 'openmarine_xml_path' => 'boat.boat_category', 'group_label' => 'Identity', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'new_or_used', 'openmarine_xml_path' => 'boat.new_or_used', 'group_label' => 'Identity', 'is_required' => false, 'notes' => "Defaults to 'used' when empty."],
            ['schepenkring_field' => 'boat_name', 'openmarine_xml_path' => 'boat.name', 'group_label' => 'Identity', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'status', 'openmarine_xml_path' => 'boat.status', 'group_label' => 'Identity', 'is_required' => false, 'notes' => 'Gates exportability — only approved/published/active pass validate().'],

            ['schepenkring_field' => 'price', 'openmarine_xml_path' => 'boat.price.amount', 'group_label' => 'Price', 'is_required' => true, 'notes' => null],
            ['schepenkring_field' => '(hardcoded "EUR")', 'openmarine_xml_path' => 'boat.price.currency', 'group_label' => 'Price', 'is_required' => false, 'notes' => 'Not read from the yacht — always EUR.'],
            ['schepenkring_field' => '(hardcoded "excluded")', 'openmarine_xml_path' => 'boat.price.vat', 'group_label' => 'Price', 'is_required' => false, 'notes' => 'Not read from the yacht — always excluded.'],

            ['schepenkring_field' => 'loa', 'openmarine_xml_path' => 'boat.dimensions.loa', 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'beam', 'openmarine_xml_path' => 'boat.dimensions.beam', 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'draft', 'openmarine_xml_path' => 'boat.dimensions.draft', 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'displacement', 'openmarine_xml_path' => 'boat.dimensions.displacement', 'group_label' => 'Dimensions', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'engine_manufacturer', 'openmarine_xml_path' => 'boat.engine.manufacturer', 'group_label' => 'Engine', 'is_required' => false, 'notes' => 'Engine block only renders if engine_manufacturer or horse_power is set.'],
            ['schepenkring_field' => 'engine_model', 'openmarine_xml_path' => 'boat.engine.model', 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'horse_power', 'openmarine_xml_path' => 'boat.engine.horsepower', 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'hours', 'openmarine_xml_path' => 'boat.engine.hours', 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'fuel', 'openmarine_xml_path' => 'boat.engine.fuel', 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'engine_year', 'openmarine_xml_path' => 'boat.engine.year', 'group_label' => 'Engine', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'short_description_nl', 'openmarine_xml_path' => 'boat.descriptions.description[lang=nl]', 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => 'validate() warns (not errors) if neither NL nor EN description is set.'],
            ['schepenkring_field' => 'short_description_en', 'openmarine_xml_path' => 'boat.descriptions.description[lang=en]', 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'short_description_de', 'openmarine_xml_path' => 'boat.descriptions.description[lang=de]', 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'short_description_fr', 'openmarine_xml_path' => 'boat.descriptions.description[lang=fr]', 'group_label' => 'Descriptions', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'location_city', 'openmarine_xml_path' => 'boat.location.city', 'group_label' => 'Location', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'location_country', 'openmarine_xml_path' => 'boat.location.country', 'group_label' => 'Location', 'is_required' => false, 'notes' => "KNOWN GAP: yachts has no location_country column — this always exports the hardcoded fallback 'NL' regardless of the yacht's actual location."],
            ['schepenkring_field' => 'location_lat', 'openmarine_xml_path' => 'boat.location.lat', 'group_label' => 'Location', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'location_lng', 'openmarine_xml_path' => 'boat.location.lng', 'group_label' => 'Location', 'is_required' => false, 'notes' => null],

            ['schepenkring_field' => 'images[].url', 'openmarine_xml_path' => 'boat.images.image.url', 'group_label' => 'Images', 'is_required' => true, 'notes' => 'validate() requires at least 1 image, warns below 3.'],
            ['schepenkring_field' => 'images[].caption', 'openmarine_xml_path' => 'boat.images.image.caption', 'group_label' => 'Images', 'is_required' => false, 'notes' => null],
            ['schepenkring_field' => 'images[].sort_order', 'openmarine_xml_path' => 'boat.images.image[@order]', 'group_label' => 'Images', 'is_required' => false, 'notes' => null],
        ];
    }
}
