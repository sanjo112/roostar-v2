<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Soft;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class TeacherMoveRule implements Rule
{
    public function id(): string
    {
        return 'soft.teacher_moves';
    }

    public function severity(): string
    {
        return 'soft';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $byTeacherAndDay = [];

        foreach ($schedule->assignments() as $assignment) {
            $slot = $input->slot($assignment->slotId);
            $room = $input->room($assignment->roomId);
            if (!$slot || !$room) {
                continue;
            }

            $byTeacherAndDay[$assignment->teacherId][$slot->dayIndex][] = [
                'period' => $slot->period,
                'room_id' => $room->id,
                'building_id' => $room->buildingId ?? $room->id,
            ];
        }

        $violations = [];

        foreach ($byTeacherAndDay as $teacherId => $days) {
            foreach ($days as $dayIndex => $lessons) {
                usort($lessons, static fn (array $a, array $b): int => $a['period'] <=> $b['period']);

                for ($i = 1; $i < count($lessons); $i++) {
                    $previous = $lessons[$i - 1];
                    $current = $lessons[$i];

                    if ($current['period'] !== $previous['period'] + 1) {
                        continue;
                    }

                    if ($current['building_id'] !== $previous['building_id']) {
                        $violations[] = new RuleViolation(
                            $this->id(),
                            $this->severity(),
                            'Docent wisselt direct van gebouw of lokaalcluster.',
                            3,
                            ['teacher_id' => $teacherId, 'day_index' => $dayIndex, 'from' => $previous['room_id'], 'to' => $current['room_id']],
                        );
                    }
                }
            }
        }

        return new ValidationResult($violations);
    }
}

