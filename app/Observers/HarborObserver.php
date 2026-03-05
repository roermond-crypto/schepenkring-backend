<?php

namespace App\Observers;

use App\Jobs\GenerateHarborPageJob;
use App\Models\Harbor;

class HarborObserver
{
    private array $sourceFields = [
        'name',
        'description',
        'facilities',
        'tags',
        'street_address',
        'postal_code',
        'city',
        'province',
        'country',
        'phone',
        'email',
        'website',
        'primary_phone',
        'google_website',
        'opening_hours_json',
        'rating',
        'rating_count',
        'lat',
        'lng',
        'gmaps_formatted_address',
        'place_details_json',
    ];

    public function updated(Harbor $harbor): void
    {
        if (empty($harbor->gmaps_place_id)) {
            return;
        }

        foreach ($this->sourceFields as $field) {
            if ($harbor->wasChanged($field)) {
                GenerateHarborPageJob::dispatch($harbor, 'nl');
                return;
            }
        }
    }
}
