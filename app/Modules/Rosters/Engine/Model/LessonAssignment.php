<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class LessonAssignment
{
    public function __construct(
        public readonly string $lessonRequestId,
        public readonly string $classGroupId,
        public readonly string $teacherId,
        public readonly string $subjectId,
        public readonly string $roomId,
        public readonly string $slotId,
    ) {
    }

    public function withSlotAndRoom(string $slotId, string $roomId): self
    {
        return new self(
            $this->lessonRequestId,
            $this->classGroupId,
            $this->teacherId,
            $this->subjectId,
            $roomId,
            $slotId,
        );
    }
}

