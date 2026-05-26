<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Soft;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class TeacherRoomPreferenceRule implements Rule
{
    public function id(): string
    {
        return 'soft.teacher_room_preferences';
    }

    public function severity(): string
    {
        return 'soft';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $violations = [];

        foreach ($schedule->assignments() as $assignment) {
            $teacher = $input->teacher($assignment->teacherId);

            if (!$teacher || $teacher->preferredRoomIds === [] || isset($teacher->preferredRoomIds[$assignment->roomId])) {
                continue;
            }

            $violations[] = new RuleViolation(
                $this->id(),
                $this->severity(),
                'Docentvoorkeur voor lokaal wordt niet gevolgd.',
                2,
                ['teacher_id' => $assignment->teacherId, 'room_id' => $assignment->roomId],
            );
        }

        return new ValidationResult($violations);
    }
}

