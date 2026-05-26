<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Optimization;

use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;

final class ImproveSpreadOptimizer extends LocalSearchOptimizer
{
    public function name(): string
    {
        return 'Betere spreiding';
    }

    protected function candidatesFor(LessonAssignment $assignment, SchedulingInput $input): array
    {
        $currentSlot = $input->slot($assignment->slotId);
        $candidates = [];

        foreach ($input->timeSlots as $slot) {
            if ($slot->id === $assignment->slotId) {
                continue;
            }

            if ($currentSlot && $slot->dayIndex === $currentSlot->dayIndex) {
                continue;
            }

            $candidates[] = $assignment->withSlotAndRoom($slot->id, $assignment->roomId);
        }

        return $candidates;
    }
}

