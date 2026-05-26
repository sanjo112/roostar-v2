<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class Teacher
{
    /**
     * @param string[] $availableSlotIds Empty means available for every slot.
     * @param array<string, int> $preferredRoomIds Room id to preference weight.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $availableSlotIds = [],
        public readonly array $preferredRoomIds = [],
    ) {
    }
}

