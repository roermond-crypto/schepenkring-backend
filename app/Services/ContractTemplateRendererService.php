<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Models\Location;
use App\Models\SignRequest;
use App\Models\Yacht;
use Illuminate\Support\Facades\Log;
use IntlDateFormatter;

class ContractTemplateRendererService
{
    public const TEMPLATE_TYPES = [
        'purchase_contract'  => 'Koopovereenkomst',
        'seller_assignment'  => 'Verkoopopdracht',
        'viewing_agreement'  => 'Bezichtigingsovereenkomst',
        'offer_agreement'    => 'Bodovereenkomst',
        'delivery_document'  => 'Leveringsdocument',
        'brokerage_agreement'=> 'Makelaarsovereenkomst',
    ];

    public const TAGS = [
        // Buyer
        'buyer_name'    => ['label' => 'Naam koper',    'category' => 'Koper',    'sample' => 'Hans Müller'],
        'buyer_address' => ['label' => 'Adres koper',   'category' => 'Koper',    'sample' => 'Hoofdstraat 12, 6041 AB Roermond'],
        'buyer_email'   => ['label' => 'E-mail koper',  'category' => 'Koper',    'sample' => 'hans@example.nl'],
        'buyer_phone'   => ['label' => 'Telefoon koper','category' => 'Koper',    'sample' => '+31612345678'],
        // Seller
        'seller_name'    => ['label' => 'Naam verkoper',    'category' => 'Verkoper', 'sample' => 'Jan de Vries'],
        'seller_address' => ['label' => 'Adres verkoper',   'category' => 'Verkoper', 'sample' => 'Kerkstraat 5, 8861 AG Harlingen'],
        'seller_email'   => ['label' => 'E-mail verkoper',  'category' => 'Verkoper', 'sample' => 'jan@example.nl'],
        'seller_phone'   => ['label' => 'Telefoon verkoper','category' => 'Verkoper', 'sample' => '+31687654321'],
        // Boat
        'boat_name'     => ['label' => 'Naam boot',         'category' => 'Vaartuig', 'sample' => 'Oomen Kruiser Fly'],
        'boat_brand'    => ['label' => 'Merk boot',         'category' => 'Vaartuig', 'sample' => 'Oomen'],
        'boat_model'    => ['label' => 'Model boot',        'category' => 'Vaartuig', 'sample' => 'Kruiser Fly 1200'],
        'boat_year'     => ['label' => 'Bouwjaar boot',     'category' => 'Vaartuig', 'sample' => '2009'],
        'boat_price'    => ['label' => 'Prijs boot',        'category' => 'Vaartuig', 'sample' => '€ 72.500'],
        'boat_location' => ['label' => 'Ligplaats boot',    'category' => 'Vaartuig', 'sample' => 'Roermond'],
        'boat_length'   => ['label' => 'Lengte boot',       'category' => 'Vaartuig', 'sample' => '12.00 m'],
        'boat_beam'     => ['label' => 'Breedte boot',      'category' => 'Vaartuig', 'sample' => '3.80 m'],
        // Broker / Location
        'broker_name'      => ['label' => 'Naam makelaar',     'category' => 'Locatie', 'sample' => 'Schepenkring Roermond'],
        'location_name'    => ['label' => 'Naam locatie',      'category' => 'Locatie', 'sample' => 'Schepenkring Roermond'],
        'location_address' => ['label' => 'Adres locatie',     'category' => 'Locatie', 'sample' => 'Havenweg 1, 6041 NM Roermond'],
        'location_email'   => ['label' => 'E-mail locatie',    'category' => 'Locatie', 'sample' => 'roermond@schepenkring.nl'],
        'location_phone'   => ['label' => 'Telefoon locatie',  'category' => 'Locatie', 'sample' => '+31475123456'],
        // Contract
        'contract_date'   => ['label' => 'Datum contract',     'category' => 'Contract', 'sample' => '24 juni 2026'],
        'signing_date'    => ['label' => 'Datum ondertekening','category' => 'Contract', 'sample' => '24 juni 2026'],
        'deposit_amount'  => ['label' => 'Aanbetaling',        'category' => 'Contract', 'sample' => '€ 7.250'],
        'final_amount'    => ['label' => 'Eindprijs',          'category' => 'Contract', 'sample' => '€ 72.500'],
        'sign_request_id' => ['label' => 'Contract-ID',        'category' => 'Contract', 'sample' => '#41'],
    ];

    /**
     * Replace {{tag}} placeholders with values from $tags array.
     * Unknown tags are left as-is.
     */
    public function replaceTags(string $html, array $tags): string
    {
        return preg_replace_callback(
            '/\{\{([a-z_]+)\}\}/',
            function (array $matches) use ($tags): string {
                $key = $matches[1];
                return array_key_exists($key, $tags) ? (string) $tags[$key] : $matches[0];
            },
            $html
        ) ?? $html;
    }

    /**
     * Return the list of tag keys that appear in the HTML.
     *
     * @return string[]
     */
    public function extractUsedTags(string $html): array
    {
        preg_match_all('/\{\{([a-z_]+)\}\}/', $html, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Return sample tag values keyed by tag name.
     *
     * @return array<string, string>
     */
    public function getSampleTags(): array
    {
        $result = [];
        foreach (self::TAGS as $key => $meta) {
            $result[$key] = $meta['sample'];
        }
        return $result;
    }

    /**
     * Find tags that appear in $html but are not present in $providedKeys.
     *
     * @param  string[]  $providedKeys
     * @return string[]
     */
    public function getMissingTags(string $html, array $providedKeys): array
    {
        $used = $this->extractUsedTags($html);
        return array_values(array_diff($used, $providedKeys));
    }

    /**
     * Wrap each missing tag occurrence in a red highlight span.
     *
     * @param  string[]  $missingKeys
     */
    public function highlightMissingTags(string $html, array $missingKeys): string
    {
        if (empty($missingKeys)) {
            return $html;
        }

        foreach ($missingKeys as $key) {
            $placeholder = '{{' . $key . '}}';
            $highlighted = '<span style="background: #fde8e8; color: #c81020; padding: 0 2px; border-radius: 2px;">{{' . $key . '}}</span>';
            $html = str_replace($placeholder, $highlighted, $html);
        }

        return $html;
    }

    /**
     * Resolve real tag values from the database for a given yacht (and optional sign request).
     * Uses try-catch per section so partial data is returned even if one section fails.
     *
     * @return array<string, string>
     */
    public function resolveTagsForYacht(int $yachtId, ?int $signRequestId = null): array
    {
        $tags = [];

        // ── Yacht / Boat ──────────────────────────────────────────────────────────
        try {
            $yacht = Yacht::with('location')->find($yachtId);

            if ($yacht) {
                $tags['boat_name']  = $yacht->boat_name ?? '';
                $tags['boat_brand'] = $yacht->manufacturer ?? '';
                $tags['boat_model'] = $yacht->model ?? '';
                $tags['boat_year']  = $yacht->year ? (string) $yacht->year : '';

                if ($yacht->price) {
                    $tags['boat_price'] = '€ ' . number_format((float) $yacht->price, 0, ',', '.');
                } else {
                    $tags['boat_price'] = '';
                }

                // Dimensions
                $dimensionModel = $yacht->dimensions ?? null;
                if ($dimensionModel) {
                    $loa  = $dimensionModel->loa ?? null;
                    $beam = $dimensionModel->beam ?? null;
                    $tags['boat_length'] = $loa  ? number_format((float) $loa,  2, '.', '') . ' m' : '';
                    $tags['boat_beam']   = $beam ? number_format((float) $beam, 2, '.', '') . ' m' : '';
                } else {
                    $tags['boat_length'] = '';
                    $tags['boat_beam']   = '';
                }

                // Location / berth name
                $location = $yacht->location;
                if ($location) {
                    $tags['boat_location']  = $location->name ?? '';
                    $tags['location_name']  = $location->name ?? '';
                    $tags['broker_name']    = $location->name ?? '';

                    $addressParts = array_filter([
                        trim(($location->address_line1 ?? '') . ' ' . ($location->street_number ?? '')),
                        trim(($location->postal_code ?? '') . ' ' . ($location->city ?? '')),
                    ]);
                    $tags['location_address'] = implode(', ', $addressParts);
                    $tags['location_email']   = $location->email ?? '';
                    $tags['location_phone']   = $location->phone ?? '';
                } else {
                    $tags['boat_location']    = $yacht->location_city ?? $yacht->vessel_lying ?? '';
                    $tags['location_name']    = '';
                    $tags['broker_name']      = '';
                    $tags['location_address'] = '';
                    $tags['location_email']   = '';
                    $tags['location_phone']   = '';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ContractTemplateRendererService] Failed to resolve yacht tags', [
                'yacht_id' => $yachtId,
                'error'    => $e->getMessage(),
            ]);
        }

        // ── Sign Request / Buyer / Seller ─────────────────────────────────────────
        if ($signRequestId !== null) {
            try {
                $signRequest = SignRequest::find($signRequestId);

                if ($signRequest) {
                    $metadata = $signRequest->metadata ?? [];

                    // Contract meta
                    $tags['sign_request_id'] = '#' . $signRequest->id;
                    $tags['contract_date']   = $this->formatDutchDate(now());
                    $tags['signing_date']    = $this->formatDutchDate(now());

                    // Buyer from metadata
                    $tags['buyer_name']    = $metadata['buyer_name']    ?? $metadata['signer_name']    ?? '';
                    $tags['buyer_address'] = $metadata['buyer_address'] ?? '';
                    $tags['buyer_email']   = $metadata['buyer_email']   ?? $metadata['signer_email']   ?? '';
                    $tags['buyer_phone']   = $metadata['buyer_phone']   ?? $metadata['signer_phone']   ?? '';

                    // Seller from metadata
                    $tags['seller_name']    = $metadata['seller_name']    ?? '';
                    $tags['seller_address'] = $metadata['seller_address'] ?? '';
                    $tags['seller_email']   = $metadata['seller_email']   ?? '';
                    $tags['seller_phone']   = $metadata['seller_phone']   ?? '';

                    // Financial from metadata
                    $depositRaw = $metadata['deposit_amount'] ?? null;
                    $finalRaw   = $metadata['final_amount']   ?? null;

                    $tags['deposit_amount'] = $depositRaw ? '€ ' . number_format((float) $depositRaw, 0, ',', '.') : '';
                    $tags['final_amount']   = $finalRaw   ? '€ ' . number_format((float) $finalRaw,   0, ',', '.') : '';
                }
            } catch (\Throwable $e) {
                Log::warning('[ContractTemplateRendererService] Failed to resolve sign request tags', [
                    'sign_request_id' => $signRequestId,
                    'error'           => $e->getMessage(),
                ]);
            }

            // ── Try ContractParty if available ─────────────────────────────────────
            try {
                if (class_exists(\App\Models\ContractParty::class)) {
                    $buyer = \App\Models\ContractParty::where('sign_request_id', $signRequestId)
                        ->where('role_type', 'buyer')
                        ->first();

                    if ($buyer) {
                        $tags['buyer_name']    = $buyer->name    ?? $tags['buyer_name']    ?? '';
                        $tags['buyer_email']   = $buyer->email   ?? $tags['buyer_email']   ?? '';
                        $tags['buyer_phone']   = $buyer->phone   ?? $tags['buyer_phone']   ?? '';

                        $buyerAddressParts = array_filter([
                            $buyer->address   ?? '',
                            trim(($buyer->postal_code ?? '') . ' ' . ($buyer->city ?? '')),
                        ]);
                        if (!empty($buyerAddressParts)) {
                            $tags['buyer_address'] = implode(', ', $buyerAddressParts);
                        }
                    }

                    $seller = \App\Models\ContractParty::where('sign_request_id', $signRequestId)
                        ->where('role_type', 'seller')
                        ->first();

                    if ($seller) {
                        $tags['seller_name']  = $seller->name  ?? $tags['seller_name']  ?? '';
                        $tags['seller_email'] = $seller->email ?? $tags['seller_email'] ?? '';
                        $tags['seller_phone'] = $seller->phone ?? $tags['seller_phone'] ?? '';

                        $sellerAddressParts = array_filter([
                            $seller->address   ?? '',
                            trim(($seller->postal_code ?? '') . ' ' . ($seller->city ?? '')),
                        ]);
                        if (!empty($sellerAddressParts)) {
                            $tags['seller_address'] = implode(', ', $sellerAddressParts);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ContractTemplateRendererService] Failed to resolve ContractParty tags', [
                    'sign_request_id' => $signRequestId,
                    'error'           => $e->getMessage(),
                ]);
            }
        } else {
            // No sign request — fill defaults for contract fields
            $tags['sign_request_id'] = '';
            $tags['contract_date']   = $this->formatDutchDate(now());
            $tags['signing_date']    = $this->formatDutchDate(now());
            $tags['deposit_amount']  = '';
            $tags['final_amount']    = '';
            $tags['buyer_name']    = '';
            $tags['buyer_address'] = '';
            $tags['buyer_email']   = '';
            $tags['buyer_phone']   = '';
            $tags['seller_name']   = '';
            $tags['seller_address']= '';
            $tags['seller_email']  = '';
            $tags['seller_phone']  = '';
        }

        return $tags;
    }

    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Format a Carbon/DateTime as Dutch long date, e.g. "24 juni 2026".
     */
    private function formatDutchDate(\DateTimeInterface $date): string
    {
        // Use IntlDateFormatter when ext-intl is available
        if (class_exists(IntlDateFormatter::class)) {
            try {
                $formatter = new IntlDateFormatter(
                    'nl_NL',
                    IntlDateFormatter::LONG,
                    IntlDateFormatter::NONE,
                    null,
                    null,
                    'd MMMM yyyy'
                );
                $result = $formatter->format($date);
                if ($result !== false) {
                    return $result;
                }
            } catch (\Throwable) {
                // Fall through to manual mapping
            }
        }

        // Fallback: manual Dutch month names
        $dutchMonths = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];

        $month = (int) $date->format('n');
        return $date->format('j') . ' ' . ($dutchMonths[$month] ?? $date->format('F')) . ' ' . $date->format('Y');
    }
}
