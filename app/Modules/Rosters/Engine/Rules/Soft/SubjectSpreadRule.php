<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Soft;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class SubjectSpreadRule implements Rule
{
    public function id(): string
    {
        return 'soft.subject_spread';
    }

    public function severity(): string
    {
        return 'soft';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $byClassSubjectDay = [];

        foreach ($schedule->assignments() as $assignment) {
            $slot = $input->slot($assignment->slotId);
            if (!$slot) {
                continue;
            }

            $key = $assignment->classGroupId . ':' . $assignment->subjectId;
            $byClassSubjectDay[$key][$slot->dayIndex] = ($byClassSubjectDay[$key][$slot->dayIndex] ?? 0) + 1;
        }

        $violations = [];

        foreach ($byClassSubjectDay as $key => $days) {
            foreach ($days as $dayIndex => $count) {
                if ($count > 2) {
                    $violations[] = new RuleViolation(
                        $this->id(),
                        $this->severity(),
                        'Vak staat te vaak op dezelfde dag.',
                        ($count - 2) * 4,
                        ['class_subject' => $key, 'day_index' => $dayIndex, 'count' => $count],
                    );
                }
            }
        }

        return new ValidationResult($violations);
    }
}

