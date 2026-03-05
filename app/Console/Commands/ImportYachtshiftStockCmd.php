<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Yacht;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportYachtshiftStockCmd extends Command
{
    protected $signature = 'yachtshift:import-stock {--url= : Optional URL to fetch from instead of the env default}';
    protected $description = 'Imports boats directly from Yachtshift XML into the Yachts table as current stock.';

    public function handle()
    {
        $this->info('Starting direct import of YachtShift stock to Yachts table...');

        $url = $this->option('url') ?: env('YACHTSHIFT_FEED_URL_1');

        if (!$url) {
            $this->error('No feed URL provided and YACHTSHIFT_FEED_URL_1 is not set.');
            return self::FAILURE;
        }

        $this->info("Fetching XML from: {$url}");

        // For default admin user assignment
        $admin = User::where('role', 'Admin')->first() ?? User::first();
        $adminId = $admin ? $admin->id : null;

        try {
            $xmlPath = tempnam(sys_get_temp_dir(), 'yachtshift_import_');
            
            // Allow @file_get_contents to fetch remote or local
            $xmlData = @file_get_contents($url);
            if (!$xmlData) {
                $this->error("Failed to fetch data from URL");
                return self::FAILURE;
            }
            
            file_put_contents($xmlPath, $xmlData);

            $reader = new \XMLReader();
            if (!$reader->open($xmlPath)) {
                $this->error("Failed to read XML file.");
                return self::FAILURE;
            }

            $imported = 0;
            $updated = 0;
            
            while ($reader->read()) {
                if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'advert') {
                    $node = simplexml_load_string($reader->readOuterXml(), 'SimpleXMLElement', LIBXML_NOCDATA);
                    if (!$node) continue;

                    $ref = (string) $node['ref'];
                    if (empty($ref)) continue;

                    $vesselId = 'YS-' . $ref;

                    $features = $node->advert_features ?? null;
                    $omx = $node->omx ?? null;
                    $bf = $node->boat_features ?? null;

                    // Extracts images
                    $images = [];
                    if (isset($node->advert_media->media)) {
                        foreach ($node->advert_media->media as $mediaObj) {
                            $mediaUrl = trim((string) $mediaObj);
                            if (!empty($mediaUrl) && !str_contains($mediaUrl, 'youtube.com') && !str_contains($mediaUrl, 'youtu.be')) {
                                $images[] = $mediaUrl;
                            }
                        }
                    }

                    // Status
                    $xmlStatus = (string) ($node->attributes()['status'] ?? '');
                    if (strtolower($xmlStatus) !== 'available') {
                        continue;
                    }
                    $dbStatus = 'For Sale';

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

                    $manufacturer = $features ? trim((string) $features->manufacturer) : null;
                    $modelName = $features ? trim((string) $features->model) : null;
                    $boatName = $getFeature(null, 'boat_name');
                    if (empty($boatName)) {
                        $boatName = trim("{$manufacturer} {$modelName}");
                    }
                    if (empty($boatName)) {
                        $boatName = "YS Boat {$ref}";
                    }

                    $year = $omx && isset($omx->basic_data->year_built) 
                        ? (int) $omx->basic_data->year_built 
                        : ($getFeature('build', 'year') ? (int) $getFeature('build', 'year') : null);

                    $vesselLying = $features ? trim((string) ($features->vessel_lying ?? '')) : '';

                    $descNl = $omx && isset($omx->text->boat_description) 
                        ? trim((string) $omx->text->boat_description) 
                        : null;

                    $priceEur = $features ? (float) $features->asking_price : null;

                    DB::beginTransaction();
                    try {
                        $yacht = Yacht::where('vessel_id', $vesselId)->first();
                        $isNew = !$yacht;
                        
                        if (!$yacht) {
                            $yacht = new Yacht();
                            $yacht->vessel_id = $vesselId;
                            $yacht->user_id = $adminId;
                        }

                        // Core fields
                        $yacht->boat_name = $boatName;
                        $yacht->manufacturer = $manufacturer;
                        $yacht->model = $modelName;
                        $yacht->boat_type = $features ? trim((string) $features->boat_type) : null;
                        $yacht->boat_category = $features ? trim((string) ($features->boat_category ?? '')) : null;
                        $yacht->new_or_used = $features ? trim((string) ($features->new_or_used ?? '')) : null;
                        $yacht->year = $year;
                        $yacht->price = $priceEur;
                        $yacht->status = $dbStatus;
                        $yacht->vessel_lying = $vesselLying;
                        $yacht->short_description_nl = $descNl;
                        $yacht->min_bid_amount = $priceEur ? $priceEur * 0.9 : 0;
                        
                        // Handle Main Image (only for new or if no main image)
                        $mainImageUrl = count($images) > 0 ? $images[0] : null;
                        if ($mainImageUrl && (!$yacht->main_image || $isNew)) {
                            $downloadedPath = $this->downloadImage($mainImageUrl, "yachts/main", "main_{$vesselId}");
                            if ($downloadedPath) {
                                if ($yacht->main_image) {
                                    Storage::disk('public')->delete($yacht->main_image);
                                }
                                $yacht->main_image = $downloadedPath;
                                $this->info("Downloaded main image for {$vesselId}");
                            }
                        }

                        $yacht->save();

                        // Map sub-table fields and let the model handle it
                        $subData = [
                            'loa' => $getDimension('loa'),
                            'beam' => $getDimension('beam'),
                            'draft' => $getDimension('draft'),
                            'hull_colour' => $getFeature('build', 'hull_colour'),
                            'hull_construction' => $getFeature('build', 'hull_construction'),
                            'cabins' => $getFeature('accommodation', 'cabins'),
                            'berths' => $getFeature('accommodation', 'berths'),
                            'engine_manufacturer' => $getFeature('engine', 'engine_manufacturer'),
                            'horse_power' => $getFeature('engine', 'horse_power'),
                            'fuel' => $getFeature('engine', 'fuel'),
                        ];

                        $yacht->saveSubTables($subData);

                        // Handle Gallery Images (re-sync if new or requested, keeping it simple: only if we have < images count or new)
                        if ($isNew && count($images) > 0) {
                            // First image is main, rest is gallery
                            $galleryUrls = array_slice($images, 1);
                            foreach ($galleryUrls as $idx => $imgUrl) {
                                $imgPath = $this->downloadImage($imgUrl, "yachts/gallery/{$vesselId}", "gallery_{$idx}");
                                if ($imgPath) {
                                    $yacht->images()->create([
                                        'url' => $imgPath,
                                        'category' => 'General',
                                        'part_name' => 'General'
                                    ]);
                                    $this->info("  Downloaded gallery image " . ($idx + 1) . "/" . count($galleryUrls));
                                }
                            }
                            $this->info("Completed gallery download for {$vesselId}");
                        }

                        DB::commit();
                        
                        if ($isNew) {
                            $imported++;
                            $this->info("Imported new yacht: {$vesselId} - {$boatName}");
                        } else {
                            $updated++;
                        }

                    } catch (\Exception $e) {
                        DB::rollBack();
                        $this->error("Failed importing boat {$vesselId}: " . $e->getMessage());
                    }
                }
            }

            $reader->close();
            if (file_exists($xmlPath)) {
                unlink($xmlPath);
            }

            $this->info("Yachtshift stock import complete! Imported: {$imported}, Updated: {$updated}");

        } catch (\Exception $e) {
            $this->error("Global error: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function downloadImage($url, $directory, $filenamePrefix)
    {
        try {
            // Small timeout to not hang forever if image is broken
            $opts = [
                "http" => [
                    "method" => "GET",
                    "timeout" => 5
                ]
            ];
            $context = stream_context_create($opts);
            $contents = @file_get_contents($url, false, $context);
            
            if ($contents) {
                // Get extension from URL or fallback
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (!$ext) $ext = 'jpg';
                
                $filename = "{$filenamePrefix}_" . time() . ".{$ext}";
                $path = "{$directory}/{$filename}";
                
                Storage::disk('public')->put($path, $contents);
                return $path;
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
