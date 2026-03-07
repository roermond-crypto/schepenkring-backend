<?php

namespace App\Services;

use App\Models\Yacht;

class VideoCaptionService
{
    public function buildCaption(Yacht $yacht): string
    {
        $name = trim((string) ($yacht->boat_name ?: $this->buildNameFromModel($yacht)));
        $year = $yacht->year ? " ({$yacht->year})" : '';
        $price = $this->formatPrice($yacht->price ?? $yacht->sale_price);
        $location = $yacht->location_city ?: ($yacht->vessel_lying ?: null);
        $lines = [];

        if ($name !== '') {
            $lines[] = $name . $year;
        }

        if ($price !== null) {
            $lines[] = $price;
        }

        if ($location) {
            $lines[] = "Located in {$location}";
        }

        if (!empty($yacht->short_description_en)) {
            $lines[] = trim($yacht->short_description_en);
        } elseif (!empty($yacht->short_description_nl)) {
            $lines[] = trim($yacht->short_description_nl);
        }

        $lines[] = '';
        $lines[] = 'View full specs & schedule viewing:';
        $lines[] = parse_url(config('app.url'), PHP_URL_HOST) ?: 'schepenkring.nl';

        return implode("\n", array_filter($lines, static fn ($line) => $line !== null));
    }

    private function buildNameFromModel(Yacht $yacht): string
    {
        $parts = array_filter([
            $yacht->manufacturer,
            $yacht->model,
        ]);
        return implode(' ', $parts);
    }

    private function formatPrice($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $amount = number_format((float) $value, 0, '.', ',');
        return '€' . $amount;
    }
}
