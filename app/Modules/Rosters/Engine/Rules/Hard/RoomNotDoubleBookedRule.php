<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Rules\Hard;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\RuleViolation;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class RoomNotDoubleBookedRule implements Rule
{
    public function id(): string
    {
        return 'hard.room_not_double_booked';
    }

    public function severity(): string
    {
        return 'hard';
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        unset($input);
        $seen = [];
        $violations = [];

        foreach ($schedule->assignments() as $assignment) {
            $key = $assignment->roomId . '@' . $assignment->slotId;

            if (isset($seen[$key])) {
                $violations[] = new RuleViolation(
                    $this->id(),
                    $this->severity(),
                    'Lokaal is dubbel geboekt.',
                    100000,
                    ['room_id' => $assignment->roomId, 'slot_id' => $assignment->slotId],
                );
            }

            $seen[$key] = true;
        }

        return new ValidationResult($violations);
    }
}

