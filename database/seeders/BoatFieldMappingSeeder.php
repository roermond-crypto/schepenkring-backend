<?php

namespace Database\Seeders;

use App\Models\BoatField;
use App\Models\BoatFieldMapping;
use Illuminate\Database\Seeder;

class BoatFieldMappingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTriStateMappings();

        foreach ($this->fieldMappings() as $internalKey => $mappings) {
            $field = BoatField::query()->firstWhere('internal_key', $internalKey);
            if (! $field) {
                continue;
            }

            foreach ($mappings as $mapping) {
                BoatFieldMapping::query()->updateOrCreate(
                    [
                        'field_id' => $field->id,
                        'source' => $mapping['source'],
                        'external_key' => $mapping['external_key'],
                        'external_value' => $mapping['external_value'],
                        'match_type' => $mapping['match_type'],
                    ],
                    [
                        'normalized_value' => $mapping['normalized_value'],
                    ],
                );
            }
        }
    }

    private function seedTriStateMappings(): void
    {
        $mappings = [
            ['source' => 'yachtshift', 'external_key' => null, 'external_value' => 'Yes', 'normalized_value' => 'yes', 'match_type' => 'exact'],
            ['source' => 'yachtshift', 'external_key' => null, 'external_value' => 'No', 'normalized_value' => 'no', 'match_type' => 'exact'],
            ['source' => 'yachtshift', 'external_key' => null, 'external_value' => 'Unknown', 'normalized_value' => 'unknown', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => 'ja', 'normalized_value' => 'yes', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => 'nee', 'normalized_value' => 'no', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => 'aanwezig', 'normalized_value' => 'yes', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => 'niet aanwezig', 'normalized_value' => 'no', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => 'geen', 'normalized_value' => 'no', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => '1', 'normalized_value' => 'yes', 'match_type' => 'exact'],
            ['source' => 'scrape', 'external_key' => null, 'external_value' => '0', 'normalized_value' => 'no', 'match_type' => 'exact'],
            ['source' => 'future_import', 'external_key' => null, 'external_value' => 'yes', 'normalized_value' => 'yes', 'match_type' => 'exact'],
            ['source' => 'future_import', 'external_key' => null, 'external_value' => 'no', 'normalized_value' => 'no', 'match_type' => 'exact'],
            ['source' => 'future_import', 'external_key' => null, 'external_value' => 'unknown', 'normalized_value' => 'unknown', 'match_type' => 'exact'],
        ];

        BoatField::query()
            ->where('field_type', 'tri_state')
            ->get()
            ->each(function (BoatField $field) use ($mappings) {
                foreach ($mappings as $mapping) {
                    BoatFieldMapping::query()->updateOrCreate(
                        [
                            'field_id' => $field->id,
                            'source' => $mapping['source'],
                            'external_key' => $mapping['external_key'],
                            'external_value' => $mapping['external_value'],
                            'match_type' => $mapping['match_type'],
                        ],
                        [
                            'normalized_value' => $mapping['normalized_value'],
                        ],
                    );
                }
            });
    }

    /**
     * @return array<string, array<int, array<string, string|null>>>
     */
    private function fieldMappings(): array
    {
        return [
            'fuel' => [
                ['source' => 'yachtshift', 'external_key' => 'fuel', 'external_value' => 'Diesel', 'normalized_value' => 'diesel', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'fuel', 'external_value' => 'Petrol', 'normalized_value' => 'petrol', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'fuel', 'external_value' => 'Gasoline', 'normalized_value' => 'petrol', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'fuel', 'external_value' => 'Electric', 'normalized_value' => 'electric', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'fuel', 'external_value' => 'Hybrid', 'normalized_value' => 'hybrid', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => 'diesel', 'normalized_value' => 'diesel', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => 'benzine', 'normalized_value' => 'petrol', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => 'petrol', 'normalized_value' => 'petrol', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => 'gasoline', 'normalized_value' => 'petrol', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => 'elektrisch', 'normalized_value' => 'electric', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => 'hybride', 'normalized_value' => 'hybrid', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Brandstof', 'external_value' => '2x diesel', 'normalized_value' => 'diesel', 'match_type' => 'contains'],
                ['source' => 'future_import', 'external_key' => 'fuel_type', 'external_value' => 'diesel', 'normalized_value' => 'diesel', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'fuel_type', 'external_value' => 'petrol', 'normalized_value' => 'petrol', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'fuel_type', 'external_value' => 'electric', 'normalized_value' => 'electric', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'fuel_type', 'external_value' => 'hybrid', 'normalized_value' => 'hybrid', 'match_type' => 'exact'],
            ],
            'engine_type' => [
                ['source' => 'yachtshift', 'external_key' => 'engine_type', 'external_value' => 'Inboard', 'normalized_value' => 'inboard', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'engine_type', 'external_value' => 'Outboard', 'normalized_value' => 'outboard', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'engine_type', 'external_value' => 'Saildrive', 'normalized_value' => 'saildrive', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'engine_type', 'external_value' => 'Sterndrive', 'normalized_value' => 'sterndrive', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'engine_type', 'external_value' => 'Stern drive', 'normalized_value' => 'sterndrive', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'binnenboord', 'normalized_value' => 'inboard', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'binnenboordmotor', 'normalized_value' => 'inboard', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'buitenboord', 'normalized_value' => 'outboard', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'buitenboordmotor', 'normalized_value' => 'outboard', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'saildrive', 'normalized_value' => 'saildrive', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'hekaandrijving', 'normalized_value' => 'sterndrive', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Type', 'external_value' => 'sterndrive', 'normalized_value' => 'sterndrive', 'match_type' => 'contains'],
                ['source' => 'future_import', 'external_key' => 'engine_type', 'external_value' => 'inboard', 'normalized_value' => 'inboard', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'engine_type', 'external_value' => 'outboard', 'normalized_value' => 'outboard', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'engine_type', 'external_value' => 'saildrive', 'normalized_value' => 'saildrive', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'engine_type', 'external_value' => 'sterndrive', 'normalized_value' => 'sterndrive', 'match_type' => 'exact'],
            ],
            'hull_construction' => [
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'GRP', 'normalized_value' => 'grp', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'Fiberglass', 'normalized_value' => 'grp', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'Steel', 'normalized_value' => 'steel', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'Aluminium', 'normalized_value' => 'aluminum', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'Aluminum', 'normalized_value' => 'aluminum', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'Wood', 'normalized_value' => 'wood', 'match_type' => 'exact'],
                ['source' => 'yachtshift', 'external_key' => 'hull_construction', 'external_value' => 'Composite', 'normalized_value' => 'composite', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'polyester', 'normalized_value' => 'grp', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'grp', 'normalized_value' => 'grp', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'glasvezel', 'normalized_value' => 'grp', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'staal', 'normalized_value' => 'steel', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'aluminium', 'normalized_value' => 'aluminum', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'alu', 'normalized_value' => 'aluminum', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'hout', 'normalized_value' => 'wood', 'match_type' => 'exact'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'mahonie', 'normalized_value' => 'wood', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'carbon', 'normalized_value' => 'composite', 'match_type' => 'contains'],
                ['source' => 'scrape', 'external_key' => 'Bouwmateriaal', 'external_value' => 'epoxy', 'normalized_value' => 'composite', 'match_type' => 'contains'],
                ['source' => 'future_import', 'external_key' => 'hull_material', 'external_value' => 'grp', 'normalized_value' => 'grp', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'hull_material', 'external_value' => 'steel', 'normalized_value' => 'steel', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'hull_material', 'external_value' => 'aluminum', 'normalized_value' => 'aluminum', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'hull_material', 'external_value' => 'wood', 'normalized_value' => 'wood', 'match_type' => 'exact'],
                ['source' => 'future_import', 'external_key' => 'hull_material', 'external_value' => 'composite', 'normalized_value' => 'composite', 'match_type' => 'exact'],
            ],
        ];
    }
}
