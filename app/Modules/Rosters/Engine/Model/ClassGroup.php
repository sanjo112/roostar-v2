<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class ClassGroup
{
    /**
     * @param string[] $availableSlotIds Empty means available for every slot.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $availableSlotIds = [],
    ) {
    }
}

