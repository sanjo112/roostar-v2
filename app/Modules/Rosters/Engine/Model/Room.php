<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class Room
{
    /**
     * @param string[] $availableSlotIds Empty means available for every slot.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?int $capacity = null,
        public readonly ?string $buildingId = null,
        public readonly array $availableSlotIds = [],
    ) {
    }
}

