<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Optimization;

use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;

final class ImprovePreferencesOptimizer extends LocalSearchOptimizer
{
    public function name(): string
    {
        return 'Voorkeuren verbeteren';
    }

    protected function candidatesFor(LessonAssignment $assignment, SchedulingInput $input): array
    {
        $teacher = $input->teacher($assignment->teacherId);

        if (!$teacher || $teacher->preferredRoomIds === []) {
            return [];
        }

        $candidates = [];
        foreach (array_keys($teacher->preferredRoomIds) as $roomId) {
            if ($roomId !== $assignment->roomId && $input->room($roomId)) {
                $candidates[] = $assignment->withSlotAndRoom($assignment->slotId, $roomId);
            }
        }

        return $candidates;
    }
}

