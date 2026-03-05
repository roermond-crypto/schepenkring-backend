<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class AttributionService
{
    private const COOKIE_REF = 'ns_ref';
    private const COOKIE_UTM_SOURCE = 'ns_utm_source';
    private const COOKIE_UTM_MEDIUM = 'ns_utm_medium';
    private const COOKIE_UTM_CAMPAIGN = 'ns_utm_campaign';
    private const COOKIE_UTM_TERM = 'ns_utm_term';
    private const COOKIE_UTM_CONTENT = 'ns_utm_content';
    private const COOKIE_GA_CLIENT_ID = 'ns_ga_cid';

    public function capture(Request $request): array
    {
        $incoming = $this->extractIncoming($request);
        $stored = $this->extractStored($request);

        $data = $stored;
        if ($incoming['has_any']) {
            $data = array_merge($stored, $incoming['data']);
            $this->persist($data);
        }

        $data['harbor_id'] = $this->parseHarborId($data['ref_code'] ?? null);

        $gaClientId = $this->resolveGaClientId($request);
        $request->attributes->set('ga_client_id', $gaClientId);
        $request->attributes->set('attribution', $data);

        return $data;
    }

    public function getAttribution(Request $request): array
    {
        $data = $request->attributes->get('attribution');
        if (is_array($data)) {
            return $data;
        }

        $stored = $this->extractStored($request);
        $stored['harbor_id'] = $this->parseHarborId($stored['ref_code'] ?? null);
        return $stored;
    }

    public function getGaClientId(Request $request): string
    {
        $clientId = $request->attributes->get('ga_client_id');
        if (is_string($clientId) && $clientId !== '') {
            return $clientId;
        }

        $clientId = $this->resolveGaClientId($request);
        $request->attributes->set('ga_client_id', $clientId);
        return $clientId;
    }

    private function extractIncoming(Request $request): array
    {
        $data = [
            'ref_code' => $request->query('ref')
                ?: $request->input('ref')
                ?: $request->input('ref_code')
                ?: $request->header('X-Ref-Code'),
            'utm_source' => $request->query('utm_source') ?: $request->input('utm_source'),
            'utm_medium' => $request->query('utm_medium') ?: $request->input('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign') ?: $request->input('utm_campaign'),
            'utm_term' => $request->query('utm_term') ?: $request->input('utm_term'),
            'utm_content' => $request->query('utm_content') ?: $request->input('utm_content'),
        ];

        $hasAny = false;
        foreach ($data as $value) {
            if ($value !== null && $value !== '') {
                $hasAny = true;
                break;
            }
        }

        return ['has_any' => $hasAny, 'data' => $data];
    }

    private function extractStored(Request $request): array
    {
        $data = [
            'ref_code' => $request->cookie(self::COOKIE_REF) ?? Session::get(self::COOKIE_REF),
            'utm_source' => $request->cookie(self::COOKIE_UTM_SOURCE) ?? Session::get(self::COOKIE_UTM_SOURCE),
            'utm_medium' => $request->cookie(self::COOKIE_UTM_MEDIUM) ?? Session::get(self::COOKIE_UTM_MEDIUM),
            'utm_campaign' => $request->cookie(self::COOKIE_UTM_CAMPAIGN) ?? Session::get(self::COOKIE_UTM_CAMPAIGN),
            'utm_term' => $request->cookie(self::COOKIE_UTM_TERM) ?? Session::get(self::COOKIE_UTM_TERM),
            'utm_content' => $request->cookie(self::COOKIE_UTM_CONTENT) ?? Session::get(self::COOKIE_UTM_CONTENT),
        ];

        return $data;
    }

    private function persist(array $data): void
    {
        $ttlDays = (int) env('HARBOR_ATTRIBUTION_TTL_DAYS', 30);
        $ttlMinutes = max(1, $ttlDays * 24 * 60);

        foreach ([
            self::COOKIE_REF => $data['ref_code'] ?? null,
            self::COOKIE_UTM_SOURCE => $data['utm_source'] ?? null,
            self::COOKIE_UTM_MEDIUM => $data['utm_medium'] ?? null,
            self::COOKIE_UTM_CAMPAIGN => $data['utm_campaign'] ?? null,
            self::COOKIE_UTM_TERM => $data['utm_term'] ?? null,
            self::COOKIE_UTM_CONTENT => $data['utm_content'] ?? null,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            Cookie::queue($key, (string) $value, $ttlMinutes);
            Session::put($key, (string) $value);
        }
    }

    private function parseHarborId(?string $ref): ?int
    {
        if (!$ref) {
            return null;
        }

        if (preg_match('/^harbor_(\d+)$/i', $ref, $matches)) {
            return (int) $matches[1];
        }

        if (ctype_digit($ref)) {
            return (int) $ref;
        }

        return null;
    }

    private function resolveGaClientId(Request $request): string
    {
        $cookieId = $request->cookie(self::COOKIE_GA_CLIENT_ID);
        if (is_string($cookieId) && $cookieId !== '') {
            return $cookieId;
        }

        $gaCookie = $request->cookie('_ga');
        if (is_string($gaCookie) && $gaCookie !== '') {
            $parts = explode('.', $gaCookie);
            if (count($parts) >= 4) {
                $clientId = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
                Cookie::queue(self::COOKIE_GA_CLIENT_ID, $clientId, 60 * 24 * 365);
                return $clientId;
            }
        }

        $clientId = random_int(1000000000, 9999999999) . '.' . time();
        Cookie::queue(self::COOKIE_GA_CLIENT_ID, $clientId, 60 * 24 * 365);
        return $clientId;
    }
}
