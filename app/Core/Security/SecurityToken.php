<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

final class SecurityToken
{
    public function __construct(
        public readonly string $selector,
        public readonly string $plainToken,
        public readonly string $publicToken,
    ) {
    }

    public static function issue(): self
    {
        $selector = bin2hex(random_bytes(16));
        $plainToken = bin2hex(random_bytes(32));

        return new self($selector, $plainToken, $selector . ':' . $plainToken);
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}

