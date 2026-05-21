<?php

declare(strict_types=1);

namespace Roostar\Modules\Navigation;

final class NavigationItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $href,
        public readonly string $icon,
        public readonly ?string $badge = null,
        public readonly bool $active = false,
    ) {
    }
}

