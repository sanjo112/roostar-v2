<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Hard;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class AvailabilityRule implements Rule
{
    public function id(): string
    {
        return 'hard.availability';
    }

    public function severity(): string
    {
        return 'hard';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $violations = [];

        foreach ($schedule->assignments() as $assignment) {
            $teacher = $input->teacher($assignment->teacherId);
            $classGroup = $input->classGroup($assignment->classGroupId);
            $room = $input->room($assignment->roomId);
            $lessonRequest = $input->lessonRequest($assignment->lessonRequestId);

            if ($teacher && $teacher->availableSlotIds !== [] && !in_array($assignment->slotId, $teacher->availableSlotIds, true)) {
                $violations[] = $this->violation('Docent is niet beschikbaar.', ['teacher_id' => $teacher->id, 'slot_id' => $assignment->slotId]);
            }

            if ($classGroup && $classGroup->availableSlotIds !== [] && !in_array($assignment->slotId, $classGroup->availableSlotIds, true)) {
                $violations[] = $this->violation('Klas is niet beschikbaar.', ['class_group_id' => $classGroup->id, 'slot_id' => $assignment->slotId]);
            }

            if ($room && $room->availableSlotIds !== [] && !in_array($assignment->slotId, $room->availableSlotIds, true)) {
                $violations[] = $this->violation('Lokaal is niet beschikbaar.', ['room_id' => $room->id, 'slot_id' => $assignment->slotId]);
            }

            if ($lessonRequest && $lessonRequest->allowedSlotIds !== [] && !in_array($assignment->slotId, $lessonRequest->allowedSlotIds, true)) {
                $violations[] = $this->violation('Les mag niet op dit lesuur geplaatst worden.', ['lesson_request_id' => $lessonRequest->id, 'slot_id' => $assignment->slotId]);
            }

            if ($lessonRequest && $lessonRequest->allowedRoomIds !== [] && !in_array($assignment->roomId, $lessonRequest->allowedRoomIds, true)) {
                $violations[] = $this->violation('Les mag niet in dit lokaal geplaatst worden.', ['lesson_request_id' => $lessonRequest->id, 'room_id' => $assignment->roomId]);
            }
        }

        return new ValidationResult($violations);
    }

    private function violation(string $message, array $context): RuleViolation
    {
        return new RuleViolation($this->id(), $this->severity(), $message, 100000, $context);
    }
}

