<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class TimeSlot
{
    public function __construct(
        public readonly string $id,
        public readonly int $dayIndex,
        public readonly int $period,
        public readonly string $label,
    ) {
    }
}

