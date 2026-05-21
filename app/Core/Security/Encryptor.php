<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

use RuntimeException;

final class Encryptor
{
    public function __construct(private readonly string $base64Key)
    {
    }

    public function encrypt(string $plainText): string
    {
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $key);

        return base64_encode($nonce . $cipherText);
    }

    public function decrypt(string $payload): string
    {
        $key = $this->key();
        $decoded = base64_decode($payload, true);

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $key);

        if ($plainText === false) {
            throw new RuntimeException('Could not decrypt payload.');
        }

        return $plainText;
    }

    private function key(): string
    {
        $key = str_starts_with($this->base64Key, 'base64:')
            ? base64_decode(substr($this->base64Key, 7), true)
            : $this->base64Key;

        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('ENCRYPTION_KEY must be 32 bytes.');
        }

        return $key;
    }
}

