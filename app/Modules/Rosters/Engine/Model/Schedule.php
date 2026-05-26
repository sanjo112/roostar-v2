<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class Schedule
{
    /**
     * @param LessonAssignment[] $assignments
     */
    public function __construct(
        private readonly array $assignments = [],
    ) {
    }

    /**
     * @return LessonAssignment[]
     */
    public function assignments(): array
    {
        return $this->assignments;
    }

    public function withAssignment(LessonAssignment $assignment): self
    {
        return new self([...$this->assignments, $assignment]);
    }

    public function replaceAssignment(string $lessonRequestId, LessonAssignment $replacement): self
    {
        return new self(array_map(
            static fn (LessonAssignment $assignment): LessonAssignment => $assignment->lessonRequestId === $lessonRequestId ? $replacement : $assignment,
            $this->assignments,
        ));
    }

    public function assignedLessonIds(): array
    {
        return array_map(static fn (LessonAssignment $assignment): string => $assignment->lessonRequestId, $this->assignments);
    }
}

