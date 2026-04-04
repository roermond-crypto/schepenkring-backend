<?php

namespace App\Console\Commands;

use App\Models\Yacht;
use App\Models\Location;
use App\Services\BoatImportValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class ScrapeSoldBoats extends Command
{
    private const MAX_BROCHURE_BYTES = 2_500_000;
    private const MAX_IMAGE_BYTES = 12_000_000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-sold-boats {--limit= : Limit the number of boats to scrape} {--page=1 : Start page} {--max-pages= : Maximum number of pages to crawl} {--update-existing : Update boats that already exist instead of skipping them}';

    /**
     * Backward-compatible alias.
     *
     * @var array<int, string>
     */
    protected $aliases = [
        'app:scrape-schepenkring-sold',
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape sold boats archive from schepenkring.nl and store in the yachts table';

    private array $locationMap = [];
    private BoatImportValidationService $validator;

    /**
     * Execute the console command.
     */
    public function handle(BoatImportValidationService $validator)
    {
        $this->validator = $validator;
        $this->info('Starting sold boats scraper...');
        
        $startPage = (int) $this->option('page');
        $maxPages = $this->option('max-pages') ? (int) $this->option('max-pages') : 1000;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        
        $this->loadLocations();
        
        $boatsProcessed = 0;
        $boatsImported = 0;
        $boatsUpdated = 0;
        $boatsSkipped = 0;
        $currentPage = $startPage;

        while ($currentPage <= $maxPages) {
            $url = "https://www.schepenkring.nl/verkochte-boten/?page-view={$currentPage}";
            $this->info("Crawling page {$currentPage}: {$url}");

            try {
                $response = Http::timeout(20)->retry(2, 300)->get($url);
                if ($response->failed()) {
                    $this->error("Failed to fetch page {$currentPage}");
                    break;
                }

                $crawler = new Crawler($response->body());
                $boatLinks = $crawler->filter('a.botenloop')->each(function (Crawler $node) {
                    return $node->attr('href');
                });
                $boatLinks = collect($boatLinks)
                    ->map(fn($href) => $this->normalizeExternalUrl($href, $url))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($boatLinks)) {
                    $this->info("No more boats found on page {$currentPage}.");
                    break;
                }

                foreach ($boatLinks as $boatUrl) {
                    if ($limit && $boatsProcessed >= $limit) {
                        $this->info("Reached limit of {$limit} boats.");
                        return 0;
                    }

                    $result = $this->scrapeBoat($boatUrl);
                    if ($result === 'imported') {
                        $boatsImported++;
                    } elseif ($result === 'updated') {
                        $boatsUpdated++;
                    } else {
                        $boatsSkipped++;
                    }
                    $boatsProcessed++;
                }

                $currentPage++;
                
                // Be nice to the server
                sleep(1);

            } catch (\Exception $e) {
                $this->error("Error on page {$currentPage}: " . $e->getMessage());
                break;
            }
        }

        $this->info("Scraping completed. Processed: {$boatsProcessed}, Imported: {$boatsImported}, Updated: {$boatsUpdated}, Skipped: {$boatsSkipped}");
        return 0;
    }

    private function loadLocations()
    {
        $locations = Location::all();
        foreach ($locations as $loc) {
            // Map common city names or aliases if needed
            $this->locationMap[strtolower($loc->name)] = $loc->id;
            // Also try to extract from city if name is different
            if ($loc->city) {
                $this->locationMap[strtolower($loc->city)] = $loc->id;
            }
        }
    }

    private function scrapeBoat(string $url): string
    {
        $this->comment("Scraping boat: {$url}");

        // Extract ID from URL: .../aanbod-boten/5000066/valk-super-falcon-45-gs/
        preg_match('/\/(\d+)\/([^\/]+)\//', $url, $matches);
        $sourceIdentifier = $matches[1] ?? Str::slug($url);
        
        $existingYacht = Yacht::where('source_identifier', $sourceIdentifier)->first();
        if ($existingYacht) {
            if (! $this->option('update-existing')) {
                $this->warn("Boat already exists: {$sourceIdentifier}. Skipping.");
                return 'skipped';
            }

            $this->warn("Boat already exists: {$sourceIdentifier}. Updating record and images.");
        }

        try {
            $response = Http::timeout(20)->retry(2, 300)->get($url);
            if ($response->failed()) {
                $this->error("Failed to fetch boat page: {$url}");
                return 'skipped';
            }

            $crawler = new Crawler($response->body());

            $pageData = $this->extractSoldBoatPageData($crawler, $url);
            $documentData = $this->fetchSupplementalDocumentData($pageData['print_url'] ?? null);
            $brochureTables = is_array($documentData['tables'] ?? null) ? $documentData['tables'] : [];

            $generalSpecs = $pageData['general_specs'];
            $sections = $pageData['sections'];

            $brochureCategorySpecs = $this->brochureTable($brochureTables, 'Basisgegevens', 'Categorie');
            $brochureLocationSpecs = $this->brochureTable($brochureTables, 'Basisgegevens', 'Ligplaats');
            $brochureGeneralSpecs = $this->brochureTable($brochureTables, 'Basisgegevens', 'Algemeen');
            $brochureDimensionSpecs = $this->brochureTable($brochureTables, 'Basisgegevens', 'Afmeting');
            $brochureBerthSpecs = $this->brochureTable($brochureTables, 'Basisgegevens', 'Slaapplaatsen');

            $generalSpecs = array_merge(
                $brochureCategorySpecs,
                $brochureLocationSpecs,
                $brochureGeneralSpecs,
                $generalSpecs
            );

            if (!isset($generalSpecs['L x B x D ca'])) {
                $brochureDimensionTriplet = $this->formatBrochureDimensionTriplet($brochureDimensionSpecs);
                if ($brochureDimensionTriplet !== null) {
                    $generalSpecs['L x B x D ca'] = $brochureDimensionTriplet;
                }
            }

            if (!isset($generalSpecs['Slaapplaatsen'])) {
                $brochureBerths = $this->formatBrochureBerths($brochureBerthSpecs);
                if ($brochureBerths !== null) {
                    $generalSpecs['Slaapplaatsen'] = $brochureBerths;
                }
            }

            foreach ($this->flattenBrochureSections($brochureTables) as $sectionTitle => $rows) {
                $sections[$sectionTitle] = ($sections[$sectionTitle] ?? []) + $rows;
            }

            $title = $this->firstNonEmptyString([
                $pageData['title'] ?? null,
                $documentData['title'] ?? null,
            ]) ?: 'Unknown Boat';
            $description = $this->firstNonEmptyString([
                $pageData['description'] ?? null,
                $brochureGeneralSpecs['Korte omschrijving'] ?? null,
                $documentData['description'] ?? null,
            ]) ?? '';
            $images = $pageData['images'];
            if (empty($images) && !empty($documentData['images']) && is_array($documentData['images'])) {
                $images = $documentData['images'];
            }

            $generalDetailSpecs = $this->findSection($sections, 'Algemeen');
            $infoSpecs = $this->findSection($sections, 'Meer informatie');
            $infoSpecs = array_merge($generalDetailSpecs, $infoSpecs);
            $accommodationSpecs = $this->findSection($sections, 'Accommodatie');
            $engineSpecs = $this->findSection($sections, 'Motor en elektra');
            $navigationSpecs = $this->findSection($sections, 'Navigatie en elektronica');
            $riggingSpecs = $this->findSection($sections, 'Tuigage');
            $deckSpecs = $this->findSection($sections, 'Uitrusting buitenom');
            $safetySpecs = $this->findSection($sections, 'Veiligheid');

            $manufacturer = $this->firstNonEmptyString([
                $brochureCategorySpecs['Merk'] ?? null,
            ]);
            $model = $this->firstNonEmptyString([
                $brochureCategorySpecs['Model'] ?? null,
            ]);
            $boatName = $this->firstNonEmptyString([
                $generalSpecs['Merk / model'] ?? null,
                trim(implode(' ', array_filter([$manufacturer, $model]))),
                $title,
            ]) ?? 'Unknown Boat';
            $dimensions = $this->parseDimensionTriplet($generalSpecs['L x B x D ca'] ?? null);
            if ($dimensions === []) {
                $dimensions = $this->withoutEmptyValues([
                    'loa' => $this->parseDimensionValue($brochureDimensionSpecs['Lengte'] ?? null),
                    'beam' => $this->parseDimensionValue($brochureDimensionSpecs['Breedte'] ?? null),
                    'draft' => $this->parseDimensionValue($brochureDimensionSpecs['Diepgang'] ?? null),
                ]);
            }
            $berthsRaw = $generalSpecs['Slaapplaatsen'] ?? ($accommodationSpecs['Slaapplaatsen'] ?? null);
            $berths = $this->parseBerthBreakdown($berthsRaw);
            [$waterTankValue, $waterTankMaterial] = $this->parseTankSpec($accommodationSpecs['Watertank & materiaal'] ?? null);
            [$wasteWaterTankValue, $wasteWaterTankMaterial] = $this->parseTankSpec($accommodationSpecs['Vuilwatertank & materiaal'] ?? null);
            [$fuelTankValue, $fuelTankMaterial] = $this->parseTankSpec($engineSpecs['Inhoud brandstoftank'] ?? null, $engineSpecs['Materiaal brandstoftank'] ?? null);
            [$cookerValue, $cookingFuelValue] = $this->parseDelimitedSpec($accommodationSpecs['Kooktoestel & brandstof'] ?? null);
            $locationName = $this->firstNonEmptyString([
                $generalSpecs['Ligplaats'] ?? null,
                $brochureLocationSpecs['Anders'] ?? null,
                $brochureLocationSpecs['Land'] ?? null,
            ]);
            $locationId = null;
            if ($locationName) {
                // Try exact match or contains
                foreach ($this->locationMap as $name => $id) {
                    if (str_contains(strtolower($locationName), $name)) {
                        $locationId = $id;
                        break;
                    }
                }
            }

            $validation = $this->validator->validate([
                'title' => $boatName,
                'manufacturer' => $manufacturer,
                'model' => $model,
                'boat_category' => $generalSpecs['Categorie'] ?? null,
                'year' => $generalSpecs['Bouwjaar'] ?? ($engineSpecs['Bouwjaar'] ?? null),
                'loa' => $dimensions['loa'] ?? null,
                'beam' => $dimensions['beam'] ?? null,
                'draft' => $dimensions['draft'] ?? $this->parseDimensionValue($infoSpecs['Diepgang'] ?? null),
                'location' => $locationName,
                'description' => $description,
                'cabins' => $accommodationSpecs['Hutten'] ?? $accommodationSpecs['Cabins'] ?? null,
                'berths' => $berths['berths'] ?? null,
            ]);

            if (!$validation['valid']) {
                $this->warn("Skipped invalid sold boat {$sourceIdentifier}: " . implode('; ', $validation['issues']));
                Log::warning("Sold boat scraper skipped invalid row", [
                    'url' => $url,
                    'source_identifier' => $sourceIdentifier,
                    'issues' => $validation['issues'],
                ]);
                return 'skipped';
            }

            // Create Yacht record
            $yacht = $existingYacht ?: new Yacht();
            $yacht->status = 'sold';
            $yacht->source = 'schepenkring_sold_archive';
            $yacht->external_url = $url;
            $yacht->source_identifier = $sourceIdentifier;

            foreach ($this->withoutEmptyValues([
                'boat_name' => $boatName,
                'boat_category' => $this->firstNonEmptyString([
                    $generalSpecs['Categorie'] ?? null,
                    $brochureCategorySpecs['Categorie'] ?? null,
                ]),
                'boat_type' => $brochureGeneralSpecs['Boottype'] ?? null,
                'new_or_used' => $brochureGeneralSpecs['Nieuw of gebruikt'] ?? null,
                'manufacturer' => $manufacturer,
                'model' => $model,
                'year' => $this->parseIntegerValue($generalSpecs['Bouwjaar'] ?? ($engineSpecs['Bouwjaar'] ?? null)),
                'vessel_lying' => $locationName,
                'location_city' => $locationName,
                'location_lat' => $this->parseDimensionValue($brochureLocationSpecs['Breedtegraad'] ?? null),
                'location_lng' => $this->parseDimensionValue($brochureLocationSpecs['Lengtegraad'] ?? null),
                'location_id' => $locationId,
                'short_description_nl' => $description,
                'print_url' => $pageData['print_url'],
                'ref_code' => $generalSpecs['Referentiecode'] ?? null,
                'reg_details' => $generalSpecs['BTW-status'] ?? null,
                'owners_comment' => $this->combineTextBlocks([
                    $documentData['description'] ?? null,
                    $this->compileSectionRemarks($sections),
                ]),
                'price' => $this->parseCurrencyValue($generalSpecs['Vraagprijs'] ?? null),
                'steering_system' => $infoSpecs['Besturing'] ?? null,
                'steering_system_location' => $infoSpecs['Plek besturing'] ?? null,
                'rudder' => $infoSpecs['Roer'] ?? null,
                'drift_restriction' => $infoSpecs['Kiel/Zwaard'] ?? null,
            ]) as $field => $value) {
                $yacht->{$field} = $value;
            }

            // Save initial record
            $yacht->save();

            if ($existingYacht && !empty($images)) {
                $this->clearYachtImages($yacht);
            }

            // Download and store images locally when possible.
            $mainImagePath = null;
            foreach ($images as $index => $imageUrl) {
                $downloadedPath = $this->downloadImage(
                    $imageUrl,
                    "yachts/imported/schepenkring/{$sourceIdentifier}",
                    "sold_{$sourceIdentifier}_{$index}"
                );

                $storedPath = $downloadedPath ?: $imageUrl;
                if ($index === 0 && $downloadedPath) {
                    $mainImagePath = $downloadedPath;
                }

                $yacht->images()->create([
                    'url' => $storedPath,
                    'category' => 'General',
                    'part_name' => 'General',
                    'status' => 'approved',
                    'original_name' => basename((string) parse_url($imageUrl, PHP_URL_PATH)),
                    'sort_order' => $index,
                ]);
            }

            if ($mainImagePath) {
                $yacht->main_image = $mainImagePath;
                $yacht->save();
            } elseif (!empty($images)) {
                $yacht->main_image = $images[0];
                $yacht->save();
            }

            $subTableData = $this->withoutEmptyValues([
                'loa' => $dimensions['loa'] ?? null,
                'beam' => $dimensions['beam'] ?? null,
                'draft' => $dimensions['draft'] ?? $this->parseDimensionValue($infoSpecs['Diepgang'] ?? null),
                'air_draft' => $this->parseDimensionValue($infoSpecs['Doorvaarthoogte normaal'] ?? null),
                'max_draft' => $this->parseDimensionValue($infoSpecs['Diepgang Maximaal'] ?? null),
                'min_draft' => $this->parseDimensionValue($infoSpecs['Diepgang minimaal'] ?? null),
                'displacement' => $this->parseDimensionValue($infoSpecs['Waterverplaatsing'] ?? null),

                'designer' => $infoSpecs['Ontwerper'] ?? null,
                'builder' => $infoSpecs['Werf'] ?? null,
                'hull_type' => $infoSpecs['Rompvorm'] ?? null,
                'hull_colour' => $infoSpecs['Rompkleur'] ?? null,
                'hull_construction' => $generalSpecs['Bouwmateriaal'] ?? ($infoSpecs['Dek en opbouw constructie'] ?? null),
                'deck_colour' => $infoSpecs['Dek en opbouw kleur'] ?? null,
                'deck_construction' => $infoSpecs['Dek en opbouw constructie'] ?? null,
                'super_structure_colour' => $infoSpecs['Dek en opbouw kleur'] ?? null,
                'super_structure_construction' => $infoSpecs['Dek en opbouw constructie'] ?? null,
                'windows' => $infoSpecs['Ramen'] ?? null,
                'flybridge' => $infoSpecs['Flybridge'] ?? null,

                'saloon' => $accommodationSpecs['Salon'] ?? null,
                'spaces_inside' => $accommodationSpecs['Indeling en verblijfsruimtes'] ?? null,
                'headroom' => $accommodationSpecs['Stahoogte'] ?? null,
                'separate_dining_area' => $accommodationSpecs['Eethoek'] ?? null,
                'engine_room' => $accommodationSpecs['Machinekamer'] ?? null,
                'berths' => $berths['berths'] ?? null,
                'berths_fixed' => $berths['berths_fixed'] ?? null,
                'berths_extra' => $berths['berths_extra'] ?? null,
                'berths_crew' => $berths['berths_crew'] ?? null,
                'interior_type' => $accommodationSpecs['Type interieur'] ?? null,
                'upholstery_color' => $accommodationSpecs['Kleur stoffering'] ?? null,
                'matrasses' => $accommodationSpecs['Matrassen'] ?? null,
                'cushions' => $accommodationSpecs['Kussens'] ?? null,
                'curtains' => $accommodationSpecs['Gordijnen'] ?? null,
                'water_tank' => $waterTankValue,
                'water_tank_material' => $waterTankMaterial,
                'waste_water_tank' => $wasteWaterTankValue,
                'waste_water_tank_material' => $wasteWaterTankMaterial,
                'waste_water_tank_gauge' => $accommodationSpecs['Vuilwatertankmeter'] ?? null,
                'water_system' => $accommodationSpecs['Watersysteem'] ?? null,
                'hot_water' => $accommodationSpecs['Warm water'] ?? null,
                'toilet' => $accommodationSpecs['Toiletten'] ?? null,
                'television' => $accommodationSpecs['TV'] ?? null,
                'cd_player' => $accommodationSpecs['Radio/CD-speler'] ?? null,
                'cooker' => $cookerValue,
                'cooking_fuel' => $cookingFuelValue,
                'oven' => $accommodationSpecs['Oven'] ?? null,
                'microwave' => $accommodationSpecs['Magnetron'] ?? null,
                'fridge' => $accommodationSpecs['Koelkast & voeding'] ?? null,
                'heating' => $accommodationSpecs['Verwarming'] ?? null,

                'engine_quantity' => $this->parseIntegerValue($engineSpecs['Aantal identieke motoren'] ?? null),
                'starting_type' => $engineSpecs['Start type'] ?? null,
                'engine_type' => $engineSpecs['Type'] ?? null,
                'engine_manufacturer' => $engineSpecs['Merk'] ?? null,
                'engine_model' => $engineSpecs['Model'] ?? null,
                'engine_serial_number' => $engineSpecs['Serienummer'] ?? null,
                'engine_year' => $this->parseIntegerValue($engineSpecs['Bouwjaar'] ?? null),
                'cylinders' => $this->parseIntegerValue($engineSpecs['Aantal cilinders'] ?? null),
                'horse_power' => $engineSpecs['Vermogen'] ?? ($generalSpecs['Motor'] ?? null),
                'hours' => $this->parseIntegerValue($engineSpecs['Draaiuren'] ?? null),
                'fuel' => $engineSpecs['Brandstof'] ?? null,
                'reversing_clutch' => $engineSpecs['Keerkoppeling'] ?? null,
                'transmission' => $engineSpecs['Overbrenging'] ?? null,
                'drive_type' => $engineSpecs['Overbrenging'] ?? null,
                'propulsion' => $engineSpecs['Voortstuwing'] ?? null,
                'cooling_system' => $engineSpecs['Koeling'] ?? null,
                'fuel_tanks_amount' => $this->parseIntegerValue($engineSpecs['Brandstoftank aantal'] ?? null),
                'fuel_tank_total_capacity' => $fuelTankValue,
                'fuel_tank_material' => $fuelTankMaterial,
                'tachometer' => $engineSpecs['Toerenteller'] ?? null,
                'oil_pressure_gauge' => $engineSpecs['Oliedrukmeter'] ?? null,
                'temperature_gauge' => $engineSpecs['Temperatuurmeter'] ?? null,
                'bow_thruster' => $engineSpecs['Boegschroef'] ?? null,
                'stern_thruster' => $engineSpecs['Hekschroef'] ?? null,
                'battery' => $engineSpecs['Accu'] ?? null,
                'battery_charger' => $engineSpecs['Acculader'] ?? null,
                'dynamo' => $engineSpecs['Dynamo'] ?? null,
                'accumonitor' => $engineSpecs['Accumonitor'] ?? null,
                'generator' => $engineSpecs['Generator'] ?? null,
                'inverter' => $engineSpecs['Omvormer'] ?? null,
                'voltmeter' => $engineSpecs['Voltmeter'] ?? null,
                'shorepower' => $engineSpecs['Walstroom'] ?? null,
                'shore_power_cable' => $engineSpecs['Walstroomkabel'] ?? null,
                'voltage' => $engineSpecs['Voltage'] ?? null,

                'compass' => $navigationSpecs['Kompas'] ?? null,
                'log_speed' => $navigationSpecs['Log/snelheid'] ?? null,
                'speed_instrument' => $navigationSpecs['Log/snelheid'] ?? null,
                'depth_instrument' => $navigationSpecs['Dieptemeter'] ?? null,
                'navigation_lights' => $navigationSpecs['Navigatieverlichting'] ?? null,
                'rudder_position_indicator' => $navigationSpecs['Roerstandaanwijzer'] ?? null,
                'gps' => $navigationSpecs['GPS'] ?? null,
                'plotter' => $navigationSpecs['Kaartplotter'] ?? null,
                'ais' => $navigationSpecs['AIS'] ?? null,
                'vhf' => $navigationSpecs['Marifoon'] ?? null,
                'autopilot' => $navigationSpecs['Autopilot'] ?? null,
                'radar' => $navigationSpecs['Radar'] ?? null,
                'fishfinder' => $navigationSpecs['Fishfinder'] ?? null,

                'sailplan_type' => $riggingSpecs['Tuigplan'] ?? null,
                'number_of_masts' => $this->parseIntegerValue($riggingSpecs['Aantal masten'] ?? null),
                'spars_material' => $riggingSpecs['Mast materiaal'] ?? null,
                'bowsprit' => $riggingSpecs['Boegspriet'] ?? null,
                'standing_rig' => $riggingSpecs['Staand want'] ?? null,
                'sail_surface_area' => $riggingSpecs['Zeiloppervlak'] ?? null,
                'sail_material' => $riggingSpecs['Zeilmateriaal'] ?? null,
                'sail_manufacturer' => $riggingSpecs['Zeilmaker'] ?? null,
                'genoa' => $riggingSpecs['Genua'] ?? null,
                'main_sail' => $riggingSpecs['Grootzeil'] ?? null,
                'mizzen' => $riggingSpecs['Bezaan'] ?? null,
                'jib' => $riggingSpecs['Fok'] ?? null,
                'spinnaker' => $riggingSpecs['Spinnaker'] ?? null,
                'gennaker' => $riggingSpecs['Gennaker'] ?? null,
                'winches' => $riggingSpecs['Lieren'] ?? null,
                'electric_winches' => $riggingSpecs['Elektrische lieren'] ?? null,

                'anchor' => $deckSpecs['Ankers & materiaal'] ?? null,
                'anchor_winch' => $deckSpecs['Ankerlier'] ?? null,
                'spray_hood' => $deckSpecs['Buiskap'] ?? null,
                'bimini' => $deckSpecs['Bimini'] ?? null,
                'outdoor_cushions' => $deckSpecs['Buitenkussens'] ?? null,
                'sea_rails' => $deckSpecs['Zeerailing'] ?? null,
                'pushpit_pullpit' => $deckSpecs['Preek- en hekstoel(en)'] ?? null,
                'swimming_platform' => $deckSpecs['Zwemplatform'] ?? null,
                'swimming_ladder' => $deckSpecs['Zwemladder'] ?? null,
                'davits' => $deckSpecs['Davits'] ?? null,
                'teak_deck' => $deckSpecs['Teakdek'] ?? null,
                'fenders' => $deckSpecs['Stootwillen, lijnen'] ?? null,
                'cockpit_table' => $deckSpecs['Kuiptafel'] ?? null,
                'dinghy' => $deckSpecs['Bijboot'] ?? null,
                'outboard_engine' => $deckSpecs['Buitenboordmotor'] ?? null,
                'trailer' => $deckSpecs['Trailer'] ?? null,
                'crane' => $deckSpecs['Kraan'] ?? null,

                'life_buoy' => $safetySpecs['Reddingsboei'] ?? null,
                'fire_extinguisher' => $safetySpecs['Brandblusser'] ?? null,
                'life_jackets' => $safetySpecs['Reddingsvesten'] ?? null,
                'bilge_pump' => $safetySpecs['Bilgepomp'] ?? null,
                'bilge_pump_manual' => $safetySpecs['Handmatige bilgepomp'] ?? null,
                'bilge_pump_electric' => $safetySpecs['Elektrische bilgepomp'] ?? null,
                'radar_reflector' => $safetySpecs['Radarreflector'] ?? null,
                'flares' => $safetySpecs['Noodvuurwerk'] ?? null,
                'epirb' => $safetySpecs['EPIRB'] ?? null,
                'mob_system' => $safetySpecs['MOB systeem'] ?? null,
                'life_raft' => $safetySpecs['Reddingsvlot'] ?? null,
                'gas_bottle_locker' => $safetySpecs['Gasbun met afvoer'] ?? null,
                'self_draining_cockpit' => $safetySpecs['Zelflozende kuip'] ?? null,
                'watertight_door' => $safetySpecs['Waterdichte deur'] ?? null,

                'motorization_summary' => $generalSpecs['Motor'] ?? null,
            ]);

            $yacht->saveSubTables($subTableData);

            $this->info("Successfully saved boat: {$title}");
            return $existingYacht ? 'updated' : 'imported';

        } catch (\Exception $e) {
            $this->error("Error scraping boat {$url}: " . $e->getMessage());
            Log::error("Scraper error for {$url}: " . $e->getMessage());
            return 'skipped';
        }
    }

    private function extractSoldBoatPageData(Crawler $crawler, string $baseUrl): array
    {
        $title = $crawler->filter('h1.vibp_topbar_title.notranslate')->count() > 0
            ? $this->normalizeSpecText($crawler->filter('h1.vibp_topbar_title.notranslate')->text(''))
            : null;

        $generalSpecs = [];
        $crawler->filter('.vibp_spec_container .vibp_spec_wrapper')->each(function (Crawler $node) use (&$generalSpecs) {
            $labelNode = $node->filter('label');
            $valueNode = $node->filter('span');
            if ($labelNode->count() === 0 || $valueNode->count() === 0) {
                return;
            }

            $label = $this->normalizeSpecText($labelNode->text(''));
            $value = $this->extractSpecValue($valueNode->first());
            if ($label !== '' && $value !== '') {
                $generalSpecs[$label] = $value;
            }
        });

        $sections = [];
        $crawler->filter('.vibp_specs_container')->each(function (Crawler $container) use (&$sections) {
            $titleNode = $container->filter('.vibp_title_of_specs')->first();
            if ($titleNode->count() === 0) {
                return;
            }

            $sectionTitle = $this->normalizeSpecText($titleNode->text(''));
            if ($sectionTitle === '') {
                return;
            }

            $rows = [];
            $container->filter('.vibp_spec_row')->each(function (Crawler $row) use (&$rows) {
                $keyNode = $row->filter('.vibp_spec_name');
                $valueNode = $row->filter('.vibp_spec_value');
                if ($keyNode->count() === 0 || $valueNode->count() === 0) {
                    return;
                }

                $key = $this->normalizeSpecText($keyNode->text(''));
                $value = $this->extractSpecValue($valueNode->first());
                if ($key !== '' && $value !== '') {
                    $rows[$key] = $value;
                }
            });

            if (!empty($rows)) {
                $sections[$sectionTitle] = $rows;
            }
        });

        $description = '';
        if ($crawler->filter('#vibp_advert_description')->count() > 0) {
            $description = $this->normalizeSpecText($crawler->filter('#vibp_advert_description')->first()->text(''));
        }

        $printUrl = null;
        foreach ([
            '.vibp_topbar_action_pdfprinten',
            '#documenten a.vibp_topbar_action_pdfprinten',
        ] as $selector) {
            if ($printUrl !== null) {
                break;
            }

            if ($crawler->filter($selector)->count() > 0) {
                $printUrl = $this->normalizeExternalUrl($crawler->filter($selector)->first()->attr('href'), $baseUrl);
            }
        }

        $images = $crawler->filter('a[id^="click_"]')->each(function (Crawler $node) {
            return $node->attr('href');
        });
        if (empty($images) && $crawler->filter('.vibp_more_images a')->count() > 0) {
            $images = $crawler->filter('.vibp_more_images a')->each(function (Crawler $node) {
                return $node->attr('href');
            });
        }
        if (empty($images) && $crawler->filter('img.vibp_secondary_media')->count() > 0) {
            $images = $crawler->filter('img.vibp_secondary_media')->each(function (Crawler $node) {
                return $node->attr('src');
            });
        }

        $images = collect($images)
            ->map(fn ($href) => $this->normalizeExternalUrl($href, $baseUrl))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'title' => $title,
            'general_specs' => $generalSpecs,
            'sections' => $sections,
            'description' => $description,
            'print_url' => $printUrl,
            'images' => $images,
        ];
    }

    private function fetchSupplementalDocumentData(?string $url): array
    {
        $documentUrl = $this->normalizeBrochureUrl($url);
        if ($documentUrl === null) {
            return [];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'sold-brochure-');
        if ($tempFile === false) {
            return [];
        }

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->withOptions([
                    'sink' => $tempFile,
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; NauticSecureBot/1.0)',
                ])
                ->get($documentUrl);

            if (!$response->successful()) {
                return [];
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $contentLength = (int) $response->header('Content-Length', 0);
            if ($contentLength > self::MAX_BROCHURE_BYTES) {
                Log::info("Skipping oversized supplemental brochure {$documentUrl}", [
                    'content_length' => $contentLength,
                ]);
                return [];
            }

            if (str_contains($contentType, 'pdf')) {
                return [];
            }

            $fileSize = @filesize($tempFile);
            if (is_int($fileSize) && $fileSize > self::MAX_BROCHURE_BYTES) {
                Log::info("Skipping oversized downloaded brochure {$documentUrl}", [
                    'file_size' => $fileSize,
                ]);
                return [];
            }

            $body = @file_get_contents($tempFile);
            if ($body === '' || str_starts_with($body, '%PDF')) {
                return [];
            }

            if (
                !str_contains($contentType, 'html')
                && !str_starts_with(ltrim($body), '<!doctype html')
                && !str_starts_with(ltrim($body), '<html')
            ) {
                return [];
            }

            $crawler = new Crawler($body, $documentUrl);
            return $this->extractBrochureHtmlData($crawler, $documentUrl);
        } catch (\Throwable $e) {
            Log::warning("Failed fetching supplemental brochure data {$documentUrl}: {$e->getMessage()}");
            return [];
        } finally {
            if (is_string($tempFile) && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function normalizeBrochureUrl(?string $url): ?string
    {
        $url = $this->normalizeSpecText($url);
        if ($url === '') {
            return null;
        }

        if (str_contains($url, 'raamposter1.php') && preg_match('/[?&]advid=([^&]+)/i', $url, $matches) !== 1) {
            return null;
        }

        if (str_contains($url, '/yachtshift/export/brochure/')) {
            $url = str_replace('/yachtshift/export/brochure/', '/yachtshift/export/brochure-html/', $url);
        }

        return $url;
    }

    private function extractBrochureHtmlData(Crawler $crawler, string $baseUrl): array
    {
        $title = null;
        if ($crawler->filter('.add-cover-heading-title')->count() > 0) {
            $title = $this->normalizeSpecText($crawler->filter('.add-cover-heading-title')->first()->text(''));
        } elseif ($crawler->filter('h4:not(.add-section-title)')->count() > 0) {
            $title = $this->normalizeSpecText($crawler->filter('h4:not(.add-section-title)')->first()->text(''));
        }

        $description = '';
        if ($crawler->filter('.add-brochure')->count() > 1) {
            $intro = $crawler->filter('.add-brochure')->eq(1);
            $parts = $intro->filter('p')->each(function (Crawler $node) {
                return $this->normalizeSpecText($node->text(''));
            });
            $parts = array_values(array_filter($parts, static fn ($value) => $value !== ''));
            $description = implode("\n\n", $parts);
        }

        $images = $crawler->filter('img[src*="/previews/"], img[src*="/uploads/"]')->each(function (Crawler $node) use ($baseUrl) {
            return $this->normalizeExternalUrl($node->attr('src'), $baseUrl);
        });
        $images = collect($images)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $tables = [];
        $domNode = $crawler->getNode(0);
        if ($domNode instanceof \DOMNode) {
            $xpath = new \DOMXPath($domNode->ownerDocument);
            $tableNodes = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " add-section-table ")]');
            foreach ($tableNodes ?: [] as $tableNode) {
                if (!$tableNode instanceof \DOMElement) {
                    continue;
                }

                $sectionTitle = 'Document';
                $sectionNodeList = $xpath->query('preceding::h4[contains(concat(" ", normalize-space(@class), " "), " add-section-title ")][1]', $tableNode);
                $sectionNode = $sectionNodeList instanceof \DOMNodeList ? $sectionNodeList->item(0) : null;
                if ($sectionNode instanceof \DOMNode) {
                    $sectionTitle = $this->normalizeSpecText($sectionNode->textContent);
                }

                $tableTitle = '';
                $headingNodeList = $xpath->query('.//tr/th[@colspan="2"][1]', $tableNode);
                $headingNode = $headingNodeList instanceof \DOMNodeList ? $headingNodeList->item(0) : null;
                if ($headingNode instanceof \DOMNode) {
                    $tableTitle = $this->normalizeSpecText($headingNode->textContent);
                }
                if ($tableTitle === '') {
                    $tableTitle = 'General';
                }

                $rows = [];
                $rowNodes = $xpath->query('.//tr[td[1] and td[2]]', $tableNode);
                foreach ($rowNodes ?: [] as $rowNode) {
                    if (!$rowNode instanceof \DOMElement) {
                        continue;
                    }

                    $cells = $xpath->query('./td', $rowNode);
                    if (!$cells || $cells->length < 2) {
                        continue;
                    }

                    $label = $this->normalizeSpecText($cells->item(0)?->textContent ?? '');
                    $value = $this->extractDomValue($cells->item(1));
                    if ($label !== '' && $value !== '') {
                        $rows[$label] = $value;
                    }
                }

                if (!empty($rows)) {
                    $tables[$sectionTitle][$tableTitle] = $rows;
                }
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'images' => $images,
            'tables' => $tables,
            'print_url' => $baseUrl,
        ];
    }

    private function extractDomValue(?\DOMNode $node): string
    {
        if (!$node instanceof \DOMNode) {
            return '';
        }

        $text = $this->normalizeSpecText($node->textContent ?? '');
        if ($text !== '') {
            return $text;
        }

        if ($node instanceof \DOMElement) {
            $html = $node->ownerDocument?->saveHTML($node) ?? '';
            if (str_contains($html, 'fa-circle-check')) {
                return 'Yes';
            }
        }

        return '';
    }

    private function brochureTable(array $tables, string $sectionNeedle, string $tableNeedle): array
    {
        $sectionNeedle = $this->normalizeSpecText($sectionNeedle);
        $tableNeedle = $this->normalizeSpecText($tableNeedle);

        foreach ($tables as $sectionTitle => $sectionTables) {
            if (!str_contains($this->normalizeSpecText($sectionTitle), $sectionNeedle)) {
                continue;
            }

            foreach ($sectionTables as $tableTitle => $rows) {
                if (str_contains($this->normalizeSpecText($tableTitle), $tableNeedle)) {
                    return is_array($rows) ? $rows : [];
                }
            }
        }

        return [];
    }

    private function brochureValue(array $tables, string $sectionNeedle, string $tableNeedle, string $labelNeedle): ?string
    {
        $rows = $this->brochureTable($tables, $sectionNeedle, $tableNeedle);
        $labelNeedle = $this->normalizeSpecText($labelNeedle);

        foreach ($rows as $label => $value) {
            if ($this->normalizeSpecText((string) $label) === $labelNeedle) {
                return is_scalar($value) ? $this->normalizeSpecText((string) $value) : null;
            }
        }

        return null;
    }

    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeSpecText(is_scalar($value) ? (string) $value : '');
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function combineTextBlocks(array $blocks): ?string
    {
        $values = [];
        foreach ($blocks as $block) {
            $normalized = $this->normalizeSpecText(is_scalar($block) ? (string) $block : '');
            if ($normalized !== '') {
                $values[] = $normalized;
            }
        }

        $values = array_values(array_unique($values));
        return empty($values) ? null : implode("\n\n", $values);
    }

    private function flattenBrochureSections(array $tables): array
    {
        $sections = [];

        foreach ($tables as $sectionTitle => $sectionTables) {
            if (!is_array($sectionTables)) {
                continue;
            }

            $rows = [];
            foreach ($sectionTables as $tableRows) {
                if (!is_array($tableRows)) {
                    continue;
                }

                foreach ($tableRows as $label => $value) {
                    $normalizedLabel = $this->normalizeSpecText(is_scalar($label) ? (string) $label : '');
                    $normalizedValue = $this->normalizeSpecText(is_scalar($value) ? (string) $value : '');

                    if ($normalizedLabel === '' || $normalizedValue === '' || array_key_exists($normalizedLabel, $rows)) {
                        continue;
                    }

                    $rows[$normalizedLabel] = $normalizedValue;
                }
            }

            if (!empty($rows)) {
                $sections[$this->normalizeSpecText((string) $sectionTitle)] = $rows;
            }
        }

        return $sections;
    }

    private function formatBrochureDimensionTriplet(array $rows): ?string
    {
        $length = $this->normalizeSpecText($rows['Lengte'] ?? '');
        $beam = $this->normalizeSpecText($rows['Breedte'] ?? '');
        $draft = $this->normalizeSpecText($rows['Diepgang'] ?? '');

        if ($length === '' || $beam === '' || $draft === '') {
            return null;
        }

        return "{$length} x {$beam} x {$draft}";
    }

    private function formatBrochureBerths(array $rows): ?string
    {
        $parts = [];

        foreach ([
            'Vast' => 'vast',
            'Extra' => 'extra',
            'Personeel' => 'personeel',
        ] as $label => $suffix) {
            $value = $this->normalizeSpecText($rows[$label] ?? '');
            if ($value !== '') {
                $parts[] = "{$value} {$suffix}";
            }
        }

        if (!empty($parts)) {
            return implode(' ', $parts);
        }

        $total = $this->normalizeSpecText($rows['Totaal'] ?? '');
        return $total !== '' ? $total : null;
    }

    private function findSection(array $sections, string $needle): array
    {
        $needle = $this->normalizeSpecText($needle);
        foreach ($sections as $title => $rows) {
            if (str_contains($this->normalizeSpecText($title), $needle)) {
                return $rows;
            }
        }

        return [];
    }

    private function extractSpecValue(Crawler $valueNode): string
    {
        $text = $this->normalizeSpecText($valueNode->text(''));
        if ($text !== '') {
            return $text;
        }

        return $valueNode->filter('img')->count() > 0 ? 'Yes' : '';
    }

    private function normalizeSpecText(?string $value): string
    {
        $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', trim($decoded));

        return trim($normalized ?? $decoded);
    }

    private function parseDimensionTriplet(?string $value): array
    {
        $text = $this->normalizeSpecText($value);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s*x\s*/iu', $text) ?: [];
        if (count($parts) < 3) {
            return [];
        }

        return array_filter([
            'loa' => $this->parseDimensionValue($parts[0] ?? null),
            'beam' => $this->parseDimensionValue($parts[1] ?? null),
            'draft' => $this->parseDimensionValue($parts[2] ?? null),
        ], static fn ($value) => $value !== null);
    }

    private function parseBerthBreakdown(?string $value): array
    {
        $text = $this->normalizeSpecText($value);
        if ($text === '') {
            return [];
        }

        $result = ['berths' => $text];
        $patterns = [
            'berths_fixed' => '/(\d+)\s*(?:vast|fixed)/iu',
            'berths_extra' => '/(\d+)\s*(?:extra)/iu',
            'berths_crew' => '/(\d+)\s*(?:personeel|crew)/iu',
        ];

        $total = 0;
        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $count = (int) $matches[1];
                $result[$field] = $count;
                $total += $count;
            }
        }

        return $result;
    }

    private function parseTankSpec(?string $capacityValue, ?string $materialValue = null): array
    {
        $capacityText = $this->normalizeSpecText($capacityValue);
        $materialText = $this->normalizeSpecText($materialValue);
        $combined = trim($capacityText . ' ' . $materialText);

        if ($combined === '') {
            return [null, null];
        }

        $capacity = $capacityText !== '' ? $capacityText : null;
        if ($capacity !== null && preg_match('/(\d+(?:[.,]\d+)?)\s*(liter|ltr|l)\b/iu', $capacity, $matches) === 1) {
            $capacity = $matches[1] . ' ' . strtolower($matches[2]);
        }

        $material = $materialText !== '' ? $materialText : null;
        if ($material === null && preg_match('/\b(rvs|staal|kunststof|aluminium|polyethyleen|inox)\b/iu', $combined, $matches) === 1) {
            $material = $matches[1];
        }

        return [$capacity, $material];
    }

    private function parseDelimitedSpec(?string $value): array
    {
        $text = $this->normalizeSpecText($value);
        if ($text === '') {
            return [null, null];
        }

        $parts = preg_split('/\s{2,}/u', $text) ?: [];
        if (count($parts) === 1) {
            return [$text, null];
        }

        return [
            $this->normalizeSpecText($parts[0] ?? ''),
            $this->normalizeSpecText(implode(' ', array_slice($parts, 1))),
        ];
    }

    private function parseIntegerValue(?string $value): ?int
    {
        $text = $this->normalizeSpecText($value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/-?\d+/', $text, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function parseCurrencyValue(?string $value): ?float
    {
        $text = $this->normalizeSpecText($value);
        if ($text === '' || str_contains(strtolower($text), 'verkocht')) {
            return null;
        }

        return $this->parseDimensionValue($text);
    }

    private function compileSectionRemarks(array $sections): ?string
    {
        $remarks = [];
        foreach ($sections as $title => $rows) {
            if (!empty($rows['Opmerkingen'])) {
                $remarks[] = "{$title}: {$rows['Opmerkingen']}";
            }
        }

        return empty($remarks) ? null : implode("\n\n", $remarks);
    }

    private function withoutEmptyValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            if (is_array($value)) {
                return !empty($value);
            }

            return true;
        });
    }

    private function parseDimensionValue(?string $value): ?float
    {
        $raw = trim((string) $value);
        $normalized = preg_replace('/[^0-9,.\-]/', '', $raw);
        if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        $numeric = (float) $normalized;
        if (preg_match('/\bcm\b/i', $raw) === 1) {
            return round($numeric / 100, 2);
        }

        return $numeric;
    }

    private function clearYachtImages(Yacht $yacht): void
    {
        $yacht->loadMissing('images');

        foreach ($yacht->images as $image) {
            $paths = array_filter([
                $image->url,
                $image->original_temp_url,
                $image->optimized_master_url,
                $image->thumb_url,
                $image->original_kept_url,
            ]);

            foreach ($paths as $path) {
                if (!$this->isAbsoluteUrl($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $yacht->images()->delete();
    }

    private function normalizeExternalUrl(?string $url, string $baseUrl): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $parts = parse_url($baseUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }

        $basePath = rtrim(dirname($parts['path'] ?? '/'), '/');
        return $origin . ($basePath ? $basePath . '/' : '/') . ltrim($url, '/');
    }

    private function downloadImage(string $url, string $directory, string $filenamePrefix): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sold-image-');
        if ($tempFile === false) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->withOptions([
                    'sink' => $tempFile,
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; NauticSecureBot/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contentLength = (int) $response->header('Content-Length', 0);
            if ($contentLength > self::MAX_IMAGE_BYTES) {
                Log::warning("Skipping oversized image {$url}", [
                    'content_length' => $contentLength,
                ]);
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (!str_contains($contentType, 'image/')) {
                return null;
            }

            $fileSize = @filesize($tempFile);
            if ($fileSize === false || $fileSize <= 0) {
                return null;
            }
            if ($fileSize > self::MAX_IMAGE_BYTES) {
                Log::warning("Skipping oversized downloaded image {$url}", [
                    'file_size' => $fileSize,
                ]);
                return null;
            }

            $ext = $this->resolveImageExtension($url, $contentType);
            $filename = "{$filenamePrefix}_" . time() . '_' . Str::random(4) . ".{$ext}";
            $path = "{$directory}/{$filename}";

            $stream = fopen($tempFile, 'rb');
            if ($stream === false) {
                return null;
            }

            Storage::disk('public')->put($path, $stream);
            fclose($stream);
            return $path;
        } catch (\Throwable $e) {
            Log::warning("Failed downloading image {$url}: {$e->getMessage()}");
            return null;
        } finally {
            if (is_string($tempFile) && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function resolveImageExtension(string $url, string $contentType): string
    {
        if (str_contains($contentType, 'image/png')) {
            return 'png';
        }
        if (str_contains($contentType, 'image/webp')) {
            return 'webp';
        }
        if (str_contains($contentType, 'image/gif')) {
            return 'gif';
        }
        if (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
            return 'jpg';
        }

        $fromUrl = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($fromUrl, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $fromUrl === 'jpeg' ? 'jpg' : $fromUrl;
        }

        return 'jpg';
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return preg_match('/^https?:\/\//i', $value) === 1;
    }
}
