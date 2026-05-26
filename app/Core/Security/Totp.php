<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

final class Totp
{
    public static function generateSecret(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    public static function getCode(string $secret, ?int $time = null): string
    {
        $time = $time ?? floor(time() / 30);
        $key = self::base32Decode($secret);
        $msg = pack('N2', 0, $time);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = (ord($hash[$offset]) & 0x7F) << 24 |
                  (ord($hash[$offset + 1]) & 0xFF) << 16 |
                  (ord($hash[$offset + 2]) & 0xFF) << 8 |
                  (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $time = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::getCode($secret, $time + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $val = strpos($alphabet, $b32[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer & (0xFF << $bitsLeft)) >> $bitsLeft);
            }
        }

        return $out;
    }
}
