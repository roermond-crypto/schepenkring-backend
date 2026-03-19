<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Yacht;
use App\Services\KnowledgeGraphService;
use Illuminate\Console\Command;

class BackfillKnowledgeEntities extends Command
{
    protected $signature = 'app:backfill-knowledge-entities
        {--type=* : Limit backfill to one or more entity types (location, yacht)}
        {--location-id=* : Limit backfill to one or more harbor/location IDs}
        {--chunk=100 : Number of records to process per chunk}';

    protected $description = 'Backfill generic knowledge entities and relationships for harbors and yachts.';

    public function handle(KnowledgeGraphService $graph): int
    {
        $types = $this->normalizeTypes((array) $this->option('type'));
        $locationIds = array_values(array_filter(array_map('intval', (array) $this->option('location-id'))));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $summary = [
            'location' => 0,
            'yacht' => 0,
        ];

        if (in_array('location', $types, true)) {
            Location::query()
                ->when($locationIds !== [], fn ($query) => $query->whereIn('id', $locationIds))
                ->orderBy('id')
                ->chunkById($chunkSize, function ($locations) use (&$summary, $graph) {
                    foreach ($locations as $location) {
                        $graph->syncLocation($location);
                        $summary['location']++;
                    }
                });
        }

        if (in_array('yacht', $types, true)) {
            Yacht::query()
                ->with([
                    'location:id,name,code,status,chat_widget_enabled,chat_widget_welcome_text,chat_widget_theme',
                    'owner:id,client_location_id',
                ])
                ->when($locationIds !== [], function ($query) use ($locationIds) {
                    $query->where(function ($builder) use ($locationIds) {
                        $builder->whereIn('location_id', $locationIds)
                            ->orWhereIn('ref_harbor_id', $locationIds);
                    });
                })
                ->orderBy('id')
                ->chunkById($chunkSize, function ($yachts) use (&$summary, $graph) {
                    foreach ($yachts as $yacht) {
                        $graph->syncYacht($yacht);
                        $summary['yacht']++;
                    }
                });
        }

        $this->info('Knowledge entity backfill completed.');
        $this->line('Types: ' . implode(', ', $types));
        $this->line('Locations synced: ' . $summary['location']);
        $this->line('Yachts synced: ' . $summary['yacht']);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $types
     * @return array<int, string>
     */
    private function normalizeTypes(array $types): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(function ($type) {
            if (! is_string($type)) {
                return null;
            }

            $type = strtolower(trim($type));

            return in_array($type, ['location', 'yacht'], true) ? $type : null;
        }, $types))));

        return $normalized === [] ? ['location', 'yacht'] : $normalized;
    }
}
