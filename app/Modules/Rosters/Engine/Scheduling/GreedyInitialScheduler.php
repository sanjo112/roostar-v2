<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Scheduling;

use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\LessonRequest;
use Roostar\Modules\Rosters\Engine\Model\Room;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Model\TimeSlot;

final class GreedyInitialScheduler implements InitialScheduler
{
    public function createInitialSchedule(SchedulingInput $input): Schedule
    {
        $schedule = new Schedule();

        foreach ($this->orderedLessonRequests($input->lessonRequests) as $lessonRequest) {
            $assignment = $this->firstAvailableAssignment($lessonRequest, $input, $schedule);

            if ($assignment) {
                $schedule = $schedule->withAssignment($assignment);
            }
        }

        return $schedule;
    }

    /**
     * @param LessonRequest[] $lessonRequests
     * @return LessonRequest[]
     */
    private function orderedLessonRequests(array $lessonRequests): array
    {
        usort($lessonRequests, static function (LessonRequest $a, LessonRequest $b): int {
            if ($a->allowBlockHours !== $b->allowBlockHours) {
                return $a->allowBlockHours ? -1 : 1;
            }

            return [$a->classGroupId, $a->subjectId, $a->id] <=> [$b->classGroupId, $b->subjectId, $b->id];
        });

        return $lessonRequests;
    }

    private function firstAvailableAssignment(LessonRequest $lessonRequest, SchedulingInput $input, Schedule $schedule): ?LessonAssignment
    {
        foreach ($this->orderedSlotsForRequest($lessonRequest, $input, $schedule) as $slot) {
            if (!$this->slotAllowed($lessonRequest, $input, $slot)) {
                continue;
            }

            foreach ($input->rooms as $room) {
                if (!$this->roomAllowed($lessonRequest, $room, $slot)) {
                    continue;
                }

                if (!$this->slotHasFreeCoreResources($schedule, $lessonRequest, $room, $slot, $input)) {
                    continue;
                }

                return new LessonAssignment(
                    $lessonRequest->id,
                    $lessonRequest->classGroupId,
                    $lessonRequest->teacherId,
                    $lessonRequest->subjectId,
                    $room->id,
                    $slot->id,
                );
            }
        }

        return null;
    }

    /**
     * @return TimeSlot[]
     */
    private function orderedSlotsForRequest(LessonRequest $lessonRequest, SchedulingInput $input, Schedule $schedule): array
    {
        if (!$lessonRequest->allowBlockHours) {
            return $input->timeSlots;
        }

        $sameSubjectAssignments = array_values(array_filter(
            $schedule->assignments(),
            static fn (LessonAssignment $assignment): bool => $assignment->classGroupId === $lessonRequest->classGroupId
                && $assignment->subjectId === $lessonRequest->subjectId,
        ));

        if (count($sameSubjectAssignments) % 2 === 0) {
            return $input->timeSlots;
        }

        $anchorAssignment = $sameSubjectAssignments[array_key_last($sameSubjectAssignments)] ?? null;
        $anchorSlot = $anchorAssignment ? $input->slot($anchorAssignment->slotId) : null;

        if (!$anchorSlot) {
            return $input->timeSlots;
        }

        $adjacent = [];
        $remaining = [];

        foreach ($input->timeSlots as $slot) {
            if ($slot->dayIndex === $anchorSlot->dayIndex && abs($slot->period - $anchorSlot->period) === 1) {
                $adjacent[] = $slot;
                continue;
            }

            $remaining[] = $slot;
        }

        usort($adjacent, static fn (TimeSlot $a, TimeSlot $b): int => abs($a->period - $anchorSlot->period) <=> abs($b->period - $anchorSlot->period));

        return [...$adjacent, ...$remaining];
    }

    private function slotAllowed(LessonRequest $lessonRequest, SchedulingInput $input, TimeSlot $slot): bool
    {
        $teacher = $input->teacher($lessonRequest->teacherId);
        $classGroup = $input->classGroup($lessonRequest->classGroupId);

        if ($lessonRequest->allowedSlotIds !== [] && !in_array($slot->id, $lessonRequest->allowedSlotIds, true)) {
            return false;
        }

        if ($teacher && $teacher->availableSlotIds !== [] && !in_array($slot->id, $teacher->availableSlotIds, true)) {
            return false;
        }

        if ($classGroup && $classGroup->availableSlotIds !== [] && !in_array($slot->id, $classGroup->availableSlotIds, true)) {
            return false;
        }

        return true;
    }

    private function roomAllowed(LessonRequest $lessonRequest, Room $room, TimeSlot $slot): bool
    {
        if ($lessonRequest->allowedRoomIds !== [] && !in_array($room->id, $lessonRequest->allowedRoomIds, true)) {
            return false;
        }

        return $room->availableSlotIds === [] || in_array($slot->id, $room->availableSlotIds, true);
    }

    private function slotHasFreeCoreResources(Schedule $schedule, LessonRequest $lessonRequest, Room $room, TimeSlot $slot, SchedulingInput $input): bool
    {
        foreach ($schedule->assignments() as $assignment) {
            if ($assignment->slotId !== $slot->id) {
                if ($lessonRequest->allowBlockHours || $assignment->classGroupId !== $lessonRequest->classGroupId || $assignment->subjectId !== $lessonRequest->subjectId) {
                    continue;
                }

                $assignedSlot = $input->slot($assignment->slotId);
                if ($assignedSlot && $assignedSlot->dayIndex === $slot->dayIndex && abs($assignedSlot->period - $slot->period) === 1) {
                    return false;
                }

                continue;
            }

            if (
                $assignment->teacherId === $lessonRequest->teacherId
                || $assignment->classGroupId === $lessonRequest->classGroupId
                || $assignment->roomId === $room->id
            ) {
                return false;
            }
        }

        return true;
    }
}
