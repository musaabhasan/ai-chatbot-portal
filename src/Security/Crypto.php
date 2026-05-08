<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use ChatbotPortal\Support\Env;
use RuntimeException;

final class Crypto
{
    public static function encrypt(string $plainText): string
    {
        $key = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainText, $nonce, $key);

        return base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Encrypted value is malformed.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::key());
        if ($plain === false) {
            throw new RuntimeException('Encrypted value could not be decrypted.');
        }

        return $plain;
    }

    private static function key(): string
    {
        $configured = Env::get('APP_KEY', '');
        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);
            if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $decoded;
            }
        }

        if (strlen($configured) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $configured;
        }

        throw new RuntimeException('APP_KEY must be a 32-byte key or base64 encoded 32-byte value.');
    }
}
