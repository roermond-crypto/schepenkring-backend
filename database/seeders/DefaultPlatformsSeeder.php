<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class DefaultPlatformsSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'name'             => 'YachtShift',
                'slug'             => 'yachtshift',
                'website_url'      => 'https://www.yachtshift.com',
                'type'             => 'api',
                'export_method'    => 'api',
                'openmarine_enabled' => true,
                'openmarine_version' => '2.0',
                'supported_countries' => ['NL', 'BE', 'DE'],
                'supported_languages' => ['nl', 'de', 'en'],
                'priority'         => 1,
                'is_active'        => true,
            ],
            [
                'name'             => 'HISWA',
                'slug'             => 'hiswa',
                'website_url'      => 'https://www.hiswa.nl',
                'type'             => 'openmarine',
                'export_method'    => 'openmarine',
                'openmarine_enabled' => true,
                'openmarine_version' => '2.0',
                'supported_countries' => ['NL'],
                'supported_languages' => ['nl'],
                'priority'         => 2,
                'is_active'        => true,
            ],
            [
                'name'             => 'Boat24',
                'slug'             => 'boat24',
                'website_url'      => 'https://www.boat24.com',
                'type'             => 'openmarine',
                'export_method'    => 'openmarine',
                'openmarine_enabled' => true,
                'openmarine_version' => '2.0',
                'supported_countries' => ['NL', 'BE', 'DE', 'FR'],
                'supported_languages' => ['nl', 'de', 'en', 'fr'],
                'priority'         => 3,
                'is_active'        => true,
            ],
            [
                'name'             => 'YachtFocus',
                'slug'             => 'yachtfocus',
                'website_url'      => 'https://www.yachtfocus.com',
                'type'             => 'openmarine',
                'export_method'    => 'openmarine',
                'openmarine_enabled' => true,
                'openmarine_version' => '2.0',
                'supported_countries' => ['NL', 'BE'],
                'supported_languages' => ['nl', 'en'],
                'priority'         => 4,
                'is_active'        => true,
            ],
            [
                'name'             => 'Obato',
                'slug'             => 'obato',
                'website_url'      => 'https://www.obato.com',
                'type'             => 'openmarine',
                'export_method'    => 'openmarine',
                'openmarine_enabled' => true,
                'openmarine_version' => '2.0',
                'supported_countries' => ['NL', 'BE', 'DE'],
                'supported_languages' => ['nl', 'de'],
                'priority'         => 5,
                'is_active'        => true,
            ],
            [
                'name'             => 'Marktplaats',
                'slug'             => 'marktplaats',
                'website_url'      => 'https://www.marktplaats.nl',
                'type'             => 'api',
                'export_method'    => 'api',
                'openmarine_enabled' => false,
                'supported_countries' => ['NL'],
                'supported_languages' => ['nl'],
                'priority'         => 6,
                'is_active'        => true,
            ],
            [
                'name'             => 'YachtWorld',
                'slug'             => 'yachtworld',
                'website_url'      => 'https://www.yachtworld.com',
                'type'             => 'openmarine',
                'export_method'    => 'openmarine',
                'openmarine_enabled' => true,
                'openmarine_version' => '2.0',
                'supported_countries' => ['NL', 'BE', 'DE', 'FR', 'GB', 'US'],
                'supported_languages' => ['nl', 'de', 'en', 'fr'],
                'priority'         => 7,
                'is_active'        => true,
            ],
        ];

        foreach ($platforms as $data) {
            Platform::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
