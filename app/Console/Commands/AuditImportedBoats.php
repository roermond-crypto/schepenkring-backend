<?php

namespace App\Console\Commands;

use App\Models\Yacht;
use App\Services\BoatImportValidationService;
use Illuminate\Console\Command;

class AuditImportedBoats extends Command
{
    protected $signature = 'app:audit-imported-boats {--source= : Filter by import source} {--limit=50 : Max invalid boats to display}';
    protected $description = 'Audit already imported yachts and report rows that look corrupted.';

    public function handle(BoatImportValidationService $validator): int
    {
        $source = $this->option('source');
        $displayLimit = max(1, (int) $this->option('limit'));

        $query = Yacht::query()
            ->setEagerLoads([])
            ->with([
                'dimensions:id,yacht_id,loa,beam,draft',
                'accommodation:id,yacht_id,cabins,berths',
            ])
            ->orderBy('id');

        if ($source) {
            $query->where('source', $source);
        } else {
            $query->whereIn('source', ['schepenkring_sold_archive', 'yachtshift']);
        }

        $checked = 0;
        $invalid = 0;
        $rows = [];

        foreach ($query->get() as $yacht) {
            $checked++;

            $validation = $validator->validate([
                'manufacturer' => $yacht->manufacturer,
                'model' => $yacht->model,
                'boat_name' => $yacht->boat_name,
                'year' => $yacht->year,
                'loa' => $yacht->dimensions?->loa,
                'beam' => $yacht->dimensions?->beam,
                'draft' => $yacht->dimensions?->draft,
                'location' => $yacht->vessel_lying ?: $yacht->location_city,
                'description' => $yacht->short_description_nl,
                'cabins' => $yacht->accommodation?->cabins,
                'berths' => $yacht->accommodation?->berths,
            ]);

            if ($validation['valid']) {
                continue;
            }

            $invalid++;
            if (count($rows) < $displayLimit) {
                $rows[] = [
                    $yacht->id,
                    $yacht->source ?? '',
                    $yacht->source_identifier ?? '',
                    $yacht->boat_name ?? '',
                    implode(' | ', array_slice($validation['issues'], 0, 3)),
                ];
            }
        }

        $this->info("Checked {$checked} imported yachts. Invalid: {$invalid}.");

        if (!empty($rows)) {
            $this->table(['ID', 'Source', 'Source ID', 'Boat', 'Issues'], $rows);
        } else {
            $this->info('No invalid imported yachts detected.');
        }

        if ($invalid > count($rows)) {
            $this->warn('Output truncated. Increase --limit to show more invalid rows.');
        }

        return self::SUCCESS;
    }
}
