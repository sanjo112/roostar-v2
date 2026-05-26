<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Soft;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class ClassMorningStartRule implements Rule
{
    public function id(): string
    {
        return 'soft.class_morning_start';
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
                $periods = array_values(array_unique(array_map('intval', $periods)));
                sort($periods);

                $firstPeriod = $periods[0] ?? null;

                if ($firstPeriod === null || $firstPeriod <= 3) {
                    continue;
                }

                $penalty = ($firstPeriod - 3) * 45;

                if ($firstPeriod >= 5) {
                    $penalty += 80;
                }

                if ((int) $dayIndex === 5) {
                    $penalty += 30;
                }

                $violations[] = new RuleViolation(
                    $this->id(),
                    $this->severity(),
                    'Klas start te laat op de dag.',
                    $penalty,
                    [
                        'class_group_id' => $classGroupId,
                        'day_index' => $dayIndex,
                        'first_period' => $firstPeriod,
                    ],
                );
            }
        }

        return new ValidationResult($violations);
    }
}
