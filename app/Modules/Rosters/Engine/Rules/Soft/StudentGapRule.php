<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Soft;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class StudentGapRule implements Rule
{
    public function id(): string
    {
        return 'soft.student_gaps';
    }

    public function severity(): string
    {
        return 'soft';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $byClassAndDay = [];

        foreach ($schedule->assignments() as $assignment) {
            $slot = $input->slot($assignment->slotId);
            if (!$slot) {
                continue;
            }

            $byClassAndDay[$assignment->classGroupId][$slot->dayIndex][] = $slot->period;
        }

        $violations = [];

        foreach ($byClassAndDay as $classGroupId => $days) {
            foreach ($days as $dayIndex => $periods) {
                $periods = array_values(array_unique($periods));
                sort($periods);

                if (count($periods) < 2) {
                    continue;
                }

                $gaps = max($periods) - min($periods) + 1 - count($periods);
                if ($gaps > 0) {
                    $violations[] = new RuleViolation(
                        $this->id(),
                        $this->severity(),
                        'Klas heeft tussenuren.',
                        $gaps * 10,
                        ['class_group_id' => $classGroupId, 'day_index' => $dayIndex, 'gaps' => $gaps],
                    );
                }
            }
        }

        return new ValidationResult($violations);
    }
}

