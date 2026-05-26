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

        foreach ($input->lessonRequests as $lessonRequest) {
            $assignment = $this->firstAvailableAssignment($lessonRequest, $input, $schedule);

            if ($assignment) {
                $schedule = $schedule->withAssignment($assignment);
            }
        }

        return $schedule;
    }

    private function firstAvailableAssignment(LessonRequest $lessonRequest, SchedulingInput $input, Schedule $schedule): ?LessonAssignment
    {
        foreach ($input->timeSlots as $slot) {
            if (!$this->slotAllowed($lessonRequest, $input, $slot)) {
                continue;
            }

            foreach ($input->rooms as $room) {
                if (!$this->roomAllowed($lessonRequest, $room, $slot)) {
                    continue;
                }

                if (!$this->slotHasFreeCoreResources($schedule, $lessonRequest, $room, $slot)) {
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

    private function slotHasFreeCoreResources(Schedule $schedule, LessonRequest $lessonRequest, Room $room, TimeSlot $slot): bool
    {
        foreach ($schedule->assignments() as $assignment) {
            if ($assignment->slotId !== $slot->id) {
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
