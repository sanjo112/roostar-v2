<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Hard;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class BlockHoursAllowedRule implements Rule
{
    public function id(): string
    {
        return 'hard.block_hours_allowed';
    }

    public function severity(): string
    {
        return 'hard';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $byClassSubjectDay = [];
        $requiredByClassSubject = [];

        foreach ($input->lessonRequests as $lessonRequest) {
            if (!$lessonRequest->allowBlockHours) {
                continue;
            }

            $key = $lessonRequest->classGroupId . ':' . $lessonRequest->subjectId;
            $requiredByClassSubject[$key] = ($requiredByClassSubject[$key] ?? 0) + 1;
        }

        foreach ($schedule->assignments() as $assignment) {
            $slot = $input->slot($assignment->slotId);
            $request = $input->lessonRequest($assignment->lessonRequestId);

            if (!$slot || !$request) {
                continue;
            }

            $key = $assignment->classGroupId . ':' . $assignment->subjectId . ':' . $slot->dayIndex;
            $byClassSubjectDay[$key][] = [
                'period' => $slot->period,
                'class_group_id' => $assignment->classGroupId,
                'subject_id' => $assignment->subjectId,
                'slot_id' => $assignment->slotId,
                'allow_block_hours' => $request->allowBlockHours,
            ];
        }

        $violations = [];
        $requiredPairs = [];
        $actualPairs = [];

        foreach ($requiredByClassSubject as $key => $lessonCount) {
            $requiredPairs[$key] = intdiv((int) $lessonCount, 2);
            $actualPairs[$key] = 0;
        }

        foreach ($byClassSubjectDay as $dayKey => $items) {
            usort($items, static fn (array $a, array $b): int => $a['period'] <=> $b['period']);

            for ($index = 1; $index < count($items); $index++) {
                if ((int) $items[$index]['period'] !== (int) $items[$index - 1]['period'] + 1) {
                    continue;
                }

                $classSubjectKey = (string) $items[$index]['class_group_id'] . ':' . (string) $items[$index]['subject_id'];

                if (!empty($items[$index]['allow_block_hours']) && !empty($items[$index - 1]['allow_block_hours'])) {
                    $actualPairs[$classSubjectKey] = ($actualPairs[$classSubjectKey] ?? 0) + 1;
                    $index++;
                    continue;
                }

                $violations[] = new RuleViolation(
                    $this->id(),
                    $this->severity(),
                    'Blokuur is niet toegestaan voor dit vak.',
                    100000,
                    [
                        'class_group_id' => $items[$index]['class_group_id'],
                        'subject_id' => $items[$index]['subject_id'],
                        'slot_id' => $items[$index]['slot_id'],
                    ],
                );
            }
        }

        foreach ($requiredPairs as $classSubjectKey => $neededPairs) {
            if ($neededPairs < 1 || ($actualPairs[$classSubjectKey] ?? 0) >= $neededPairs) {
                continue;
            }

            [$classGroupId, $subjectId] = explode(':', (string) $classSubjectKey, 2);
            $violations[] = new RuleViolation(
                $this->id(),
                $this->severity(),
                'Blokuur is vereist voor dit vak, maar niet als aansluitend paar ingepland.',
                100000,
                [
                    'class_group_id' => $classGroupId,
                    'subject_id' => $subjectId,
                ],
            );
        }

        return new ValidationResult($violations);
    }
}
