<?php

namespace App\Services;

use App\Models\Yacht;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class YachtshiftImportService
{
    /**
     * Imports boats directly from a Yachtshift XML feed into the Yachts table as current stock.
     * 
     * @param string $url The XML feed URL
     * @return array Result summary with imported, updated, and error counts
     */
    public function importFromUrl(string $url): array
    {
        // Increase memory and time limit for large XML files and image downloads
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        Log::info("Starting direct import of YachtShift stock to Yachts table from: {$url}");

        // For default admin user assignment
        $admin = User::where('type', 'Admin')->first() ?? User::first();
        $adminId = $admin ? $admin->id : null;

        $imported = 0;
        $updated = 0;
        $errors = 0;

        try {
            $xmlPath = tempnam(sys_get_temp_dir(), 'yachtshift_import_');
            
            // Fetch remote or local
            $xmlData = @file_get_contents($url);
            if (!$xmlData) {
                Log::error("YachtshiftImportService: Failed to fetch data from URL: {$url}");
                return ['success' => false, 'message' => "Failed to fetch data from URL", 'imported' => 0, 'updated' => 0, 'errors' => 1];
            }
            
            file_put_contents($xmlPath, $xmlData);

            $reader = new \XMLReader();
            if (!$reader->open($xmlPath)) {
                Log::error("YachtshiftImportService: Failed to read XML file.");
                return ['success' => false, 'message' => "Failed to read XML file", 'imported' => 0, 'updated' => 0, 'errors' => 1];
            }
            
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
                                Log::info("Downloaded main image for {$vesselId}");
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

                        if (method_exists($yacht, 'saveSubTables')) {
                            $yacht->saveSubTables($subData);
                        }

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
                                }
                            }
                            Log::info("Completed gallery download for {$vesselId}");
                        }

                        DB::commit();
                        
                        if ($isNew) {
                            $imported++;
                            Log::info("Imported new yacht: {$vesselId} - {$boatName}");
                        } else {
                            $updated++;
                        }

                    } catch (\Exception $e) {
                        DB::rollBack();
                        $errors++;
                        Log::error("Failed importing boat {$vesselId}: " . $e->getMessage());
                    }
                }
            }

            $reader->close();
            if (file_exists($xmlPath)) {
                unlink($xmlPath);
            }

            Log::info("Yachtshift stock import complete! Imported: {$imported}, Updated: {$updated}, Errors: {$errors}");
            
            return [
                'success' => true, 
                'imported' => $imported, 
                'updated' => $updated, 
                'errors' => $errors,
                'message' => 'Import successful'
            ];

        } catch (\Exception $e) {
            Log::error("YachtshiftImportService Global error: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => $e->getMessage(),
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors + 1
            ];
        }
    }

    private function downloadImage($url, $directory, $filenamePrefix)
    {
        try {
            // Small timeout to not hang forever if image is broken
            $opts = [
                "http" => [
                    "method" => "GET",
                    "timeout" => 10
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
