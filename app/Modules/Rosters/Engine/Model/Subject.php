<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class Subject
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $code = null,
    ) {
    }
}

