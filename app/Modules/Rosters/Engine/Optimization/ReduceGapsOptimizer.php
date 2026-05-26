<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Optimization;

use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;

final class ReduceGapsOptimizer extends LocalSearchOptimizer
{
    public function name(): string
    {
        return 'Minder tussenuren';
    }

    protected function candidatesFor(LessonAssignment $assignment, SchedulingInput $input): array
    {
        $candidates = [];

        foreach ($input->timeSlots as $slot) {
            if ($slot->id === $assignment->slotId) {
                continue;
            }

            $candidates[] = $assignment->withSlotAndRoom($slot->id, $assignment->roomId);
        }

        return $candidates;
    }
}

