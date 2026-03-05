<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FetchYachtshiftBoatsCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yachtshift:fetch {--limit= : Max number of pages to fetch} {--file= : Path to a local XML file} {--dir= : Path to a directory containing XML files}';
    protected $description = 'Fetches all raw boat listings from YachtShift API or local OpenMarine XML files and stores them for normalization.';

    public function handle()
    {
        $this->info('Starting YachtShift OpenMarine XML import...');
        
        $filePath = $this->option('file');
        $dirPath = $this->option('dir');
        
        $feedUrls = [];
        if ($filePath) {
            $feedUrls[] = $filePath;
        } elseif ($dirPath) {
            if (is_dir($dirPath)) {
                $files = glob(rtrim($dirPath, '/') . '/*.xml');
                if ($files !== false) {
                    $feedUrls = array_merge($feedUrls, $files);
                }
            } else {
                $this->error("Directory not found: " . $dirPath);
                return self::FAILURE;
            }
        } else {
            $feedUrls = config('services.yachtshift.feed_urls', []);
        }
        
        if (empty($feedUrls)) {
            $this->warn('No feed URLs/files found. Faking response for development mode.');
            $this->fakeImport();
            return self::SUCCESS;
        }

        $totalImported = 0;

        foreach ($feedUrls as $endpoint) {
            $this->info("Fetching feed/file: {$endpoint}");
            
            try {
                if (file_exists($endpoint)) {
                    $xmlPath = $endpoint;
                } else {
                    $xmlPath = tempnam(sys_get_temp_dir(), 'yachtshift_');
                    file_put_contents($xmlPath, @file_get_contents($endpoint));
                }
                
                $reader = new \XMLReader();
                if (!$reader->open($xmlPath)) {
                    $this->error("Failed to open XML: " . $endpoint);
                    continue;
                }

                while ($reader->read()) {
                    if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'advert') {
                        $node = simplexml_load_string($reader->readOuterXml(), 'SimpleXMLElement', LIBXML_NOCDATA);
                        if (!$node) continue;

                        $ref = (string) $node['ref'];
                        if (empty($ref)) continue;

                        $features = $node->advert_features ?? null;
                        $omx = $node->omx ?? null;
                        $bf = $node->boat_features ?? null;

                        // Extract Images (filter only real image URLs)
                        $images = [];
                        if (isset($node->advert_media->media)) {
                            foreach ($node->advert_media->media as $mediaObj) {
                                $mediaUrl = trim((string) $mediaObj);
                                if (!empty($mediaUrl) && !str_contains($mediaUrl, 'youtube.com') && !str_contains($mediaUrl, 'youtu.be')) {
                                    $images[] = $mediaUrl;
                                }
                            }
                        }

                        // Status from XML attributes
                        $attrs = $node->attributes();
                        $status = (string) ($attrs['status'] ?? 'Active');

                        // Helper to get a boat_features item
                        $getFeature = function($section, $name) use ($bf) {
                            if (!$bf) return null;
                            $parent = $section ? ($bf->$section ?? $bf) : $bf;
                            foreach ($parent->item ?? [] as $item) {
                                if ((string) $item->attributes()['name'] === $name) {
                                    $val = trim((string) $item);
                                    return $val !== '' ? $val : null;
                                }
                            }
                            return null;
                        };

                        // Helper to get dimension and convert from cm to meters
                        $getDimension = function($name) use ($bf) {
                            if (!$bf || !$bf->dimensions) return null;
                            foreach ($bf->dimensions->item ?? [] as $item) {
                                if ((string) $item->attributes()['name'] === $name) {
                                    $val = trim((string) $item);
                                    if ($val !== '' && is_numeric($val)) {
                                        $unit = (string) ($item->attributes()['unit'] ?? 'centimetres');
                                        if ($unit === 'centimetres') {
                                            return round((float) $val / 100, 2);
                                        }
                                        return (float) $val;
                                    }
                                }
                            }
                            return null;
                        };

                        // Location
                        $vesselLying = $features ? trim((string) ($features->vessel_lying ?? '')) : '';
                        $country = ($features && $features->vessel_lying) 
                            ? (string) ($features->vessel_lying->attributes()['country'] ?? '') 
                            : '';

                        // External URL
                        $externalUrl = '';
                        if ($features && isset($features->other)) {
                            foreach ($features->other->item ?? [] as $item) {
                                if ((string) $item->attributes()['name'] === 'external_url') {
                                    $externalUrl = (string) $item;
                                }
                            }
                        }

                        // Map ALL OpenMarine XML fields to the payload
                        $payload = [
                            'id' => $ref,
                            'status' => $status,
                            'make' => $features ? trim((string) $features->manufacturer) : null,
                            'model' => $features ? trim((string) $features->model) : null,
                            'type' => $features ? trim((string) $features->boat_type) : null,
                            'boat_category' => $features ? trim((string) ($features->boat_category ?? '')) : null,
                            'boat_name' => $getFeature(null, 'boat_name'),
                            'new_or_used' => $features ? trim((string) ($features->new_or_used ?? '')) : null,
                            'engine_make' => $getFeature('engine', 'engine_manufacturer'),
                            'horse_power' => $getFeature('engine', 'horse_power'),
                            'fuel' => $getFeature('engine', 'fuel'),
                            'year' => $omx && isset($omx->basic_data->year_built) 
                                ? (int) $omx->basic_data->year_built 
                                : ($getFeature('build', 'year') ? (int) $getFeature('build', 'year') : null),
                            'length' => $getDimension('loa'),
                            'beam' => $getDimension('beam'),
                            'draft' => $getDimension('draft'),
                            'hull_colour' => $getFeature('build', 'hull_colour'),
                            'hull_construction' => $getFeature('build', 'hull_construction'),
                            'cabins' => $getFeature('accommodation', 'cabins'),
                            'berths' => $getFeature('accommodation', 'berths'),
                            'price_eur' => $features ? (float) $features->asking_price : null,
                            'currency' => $features && $features->asking_price 
                                ? (string) ($features->asking_price->attributes()['currency'] ?? 'EUR') 
                                : 'EUR',
                            'location' => $vesselLying,
                            'country' => $country,
                            'external_url' => $externalUrl,
                            'description' => $omx && isset($omx->text->boat_description) 
                                ? trim((string) $omx->text->boat_description) 
                                : null,
                            'images' => $images
                        ];

                        \Illuminate\Support\Facades\DB::table('yachtshift_raw_boats')->updateOrInsert(
                            ['yachtshift_id' => $ref],
                            [
                                'raw_payload' => json_encode($payload),
                                'status' => 'imported',
                                'updated_at' => now(),
                                'created_at' => \Illuminate\Support\Facades\DB::raw('IFNULL(created_at, NOW())')
                            ]
                        );
                        $totalImported++;
                    }
                }
                $reader->close();
                
                if (!file_exists($endpoint) && file_exists($xmlPath)) {
                    unlink($xmlPath);
                }

            } catch (\Exception $e) {
                $this->error("Exception parsing {$endpoint}: " . $e->getMessage());
            }
        }

        $this->info("Import complete. Total raw boats fetched/updated: {$totalImported}");
        return self::SUCCESS;
    }

    private function fakeImport()
    {
        // Generates 10 fake boats for local RAG testing
        $brands = ['Beneteau', 'Bénéteau', 'Jeanneau', 'Bavaria', 'Lagoon'];
        $models = ['Oceanis 38.1', 'Sun Odyssey 410', 'Cruiser 46', 'Lagoon 42'];
        $types = ['Sailing Yacht', 'Catamaran', 'Motor Yacht'];

        for ($i = 0; $i < 10; $i++) {
            $fakeId = 'ys-' . (1000 + $i);
            $payload = [
                'id' => $fakeId,
                'make' => $brands[array_rand($brands)],
                'model' => $models[array_rand($models)],
                'type' => $types[array_rand($types)],
                'year' => rand(1995, 2024),
                'length' => rand(9, 15) . '.' . rand(0, 99),
                'price_eur' => rand(50000, 500000),
                'description' => 'Beautiful ' . $types[array_rand($types)] . ' ready to sail. Call 555-0198.',
                'images' => [
                    'https://example.com/boat1.jpg',
                    'https://example.com/boat2.jpg'
                ]
            ];

            \Illuminate\Support\Facades\DB::table('yachtshift_raw_boats')->updateOrInsert(
                ['yachtshift_id' => $fakeId],
                [
                    'raw_payload' => json_encode($payload),
                    'status' => 'imported',
                    'updated_at' => now(),
                    'created_at' => \Illuminate\Support\Facades\DB::raw('IFNULL(created_at, NOW())')
                ]
            );
        }
        $this->info("Fake import complete. 10 stub boats added to raw tables.");
    }
}
