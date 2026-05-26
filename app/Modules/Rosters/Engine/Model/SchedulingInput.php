<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Model;

final class SchedulingInput
{
    /**
     * @param TimeSlot[] $timeSlots
     * @param ClassGroup[] $classGroups
     * @param Teacher[] $teachers
     * @param Subject[] $subjects
     * @param Room[] $rooms
     * @param LessonRequest[] $lessonRequests
     */
    public function __construct(
        public readonly array $timeSlots,
        public readonly array $classGroups,
        public readonly array $teachers,
        public readonly array $subjects,
        public readonly array $rooms,
        public readonly array $lessonRequests,
    ) {
    }

    public function slot(string $id): ?TimeSlot
    {
        return $this->findById($this->timeSlots, $id);
    }

    public function teacher(string $id): ?Teacher
    {
        return $this->findById($this->teachers, $id);
    }

    public function classGroup(string $id): ?ClassGroup
    {
        return $this->findById($this->classGroups, $id);
    }

    public function subject(string $id): ?Subject
    {
        return $this->findById($this->subjects, $id);
    }

    public function room(string $id): ?Room
    {
        return $this->findById($this->rooms, $id);
    }

    public function lessonRequest(string $id): ?LessonRequest
    {
        return $this->findById($this->lessonRequests, $id);
    }

    private function findById(array $items, string $id): mixed
    {
        foreach ($items as $item) {
            if (($item->id ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }
}

