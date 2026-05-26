<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Optimization;

use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;

final class ReduceMovesOptimizer extends LocalSearchOptimizer
{
    public function name(): string
    {
        return 'Minder verplaatsingen';
    }

    protected function candidatesFor(LessonAssignment $assignment, SchedulingInput $input): array
    {
        $teacher = $input->teacher($assignment->teacherId);
        $preferredRoomIds = $teacher ? array_keys($teacher->preferredRoomIds) : [];
        $candidateRoomIds = $preferredRoomIds !== [] ? $preferredRoomIds : array_map(static fn ($room): string => $room->id, $input->rooms);
        $candidates = [];

        foreach ($candidateRoomIds as $roomId) {
            if ($roomId === $assignment->roomId) {
                continue;
            }

            if (!$input->room($roomId)) {
                continue;
            }

            $candidates[] = $assignment->withSlotAndRoom($assignment->slotId, $roomId);
        }

        return $candidates;
    }
}

