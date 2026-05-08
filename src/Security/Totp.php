<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

final class Totp
{
    public static function verify(string $base32Secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $secret = self::base32Decode($base32Secret);
        $timeSlice = (int) floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::code($secret, $timeSlice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function code(string $secret, int $timeSlice): string
    {
        $counter = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $counter, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $value = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value) ?? '');
        $bits = '';

        foreach (str_split($value) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
