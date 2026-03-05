<?php

namespace App\Services;

class PhoneNumberService
{
    public function normalize(?string $number): ?string
    {
        if (!$number) {
            return null;
        }

        $clean = trim($number);
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }

        $clean = preg_replace('/[^0-9+]/', '', $clean);
        if (!$clean) {
            return null;
        }

        if (!str_starts_with($clean, '+')) {
            $dial = config('voice.default_country_dial_code');
            if ($dial) {
                $clean = '+' . preg_replace('/[^0-9]/', '', (string) $dial) . $clean;
            }
        }

        return $clean;
    }
}
