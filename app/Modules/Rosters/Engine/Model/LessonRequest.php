<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class LessonRequest
{
    /**
     * @param string[] $allowedSlotIds Empty means every slot is allowed.
     * @param string[] $allowedRoomIds Empty means every room is allowed.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $classGroupId,
        public readonly string $teacherId,
        public readonly string $subjectId,
        public readonly int $durationPeriods = 1,
        public readonly array $allowedSlotIds = [],
        public readonly array $allowedRoomIds = [],
        public readonly bool $allowBlockHours = false,
    ) {
    }
}
