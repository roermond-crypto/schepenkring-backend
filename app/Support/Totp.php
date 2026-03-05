<?php

namespace App\Support;

class Totp
{
    public static function verify(string $secret, string $code, int $window = 1, int $period = 30): bool
    {
        $code = preg_replace('/\s+/', '', $code ?? '');

        if ($code === '' || ! ctype_digit($code)) {
            return false;
        }

        $timeStep = (int) floor(time() / $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::generate($secret, $timeStep + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function generate(string $secret, int $timeStep, int $digits = 6): string
    {
        $key = self::base32Decode($secret);
        $binaryTime = pack('N*', 0).pack('N*', $timeStep);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );
        $mod = $value % (10 ** $digits);

        return str_pad((string) $mod, $digits, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $secret = preg_replace('/[^A-Z2-7]/', '', $secret);
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0, $length = strlen($secret); $i < $length; $i++) {
            $value = strpos($alphabet, $secret[$i]);

            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $result;
    }
}
