<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Hard;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class AllLessonsScheduledRule implements Rule
{
    public function id(): string
    {
        return 'hard.all_lessons_scheduled';
    }

    public function severity(): string
    {
        return 'hard';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $assigned = $schedule->assignedLessonIds();
        $violations = [];

        foreach ($input->lessonRequests as $lessonRequest) {
            if (!in_array($lessonRequest->id, $assigned, true)) {
                $violations[] = new RuleViolation(
                    $this->id(),
                    $this->severity(),
                    'Lesaanvraag is nog niet ingepland.',
                    100000,
                    ['lesson_request_id' => $lessonRequest->id],
                );
            }
        }

        return new ValidationResult($violations);
    }
}

