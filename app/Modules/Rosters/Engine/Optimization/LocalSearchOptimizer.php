<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Optimization;

use Roostar\Modules\Rosters\Engine\Contracts\Optimizer;
use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Scoring\ScheduleScorer;

abstract class LocalSearchOptimizer implements Optimizer
{
    public function optimize(Schedule $schedule, SchedulingInput $input, ScheduleScorer $scorer): Schedule
    {
        $bestSchedule = $schedule;
        $bestScore = $scorer->score($schedule, $input);

        foreach ($schedule->assignments() as $assignment) {
            foreach ($this->candidatesFor($assignment, $input) as $candidate) {
                $candidateSchedule = $schedule->replaceAssignment($assignment->lessonRequestId, $candidate);
                $candidateScore = $scorer->score($candidateSchedule, $input);

                if ($candidateScore->isBetterThan($bestScore)) {
                    $bestSchedule = $candidateSchedule;
                    $bestScore = $candidateScore;
                }
            }
        }

        return $bestSchedule;
    }

    /**
     * @return LessonAssignment[]
     */
    abstract protected function candidatesFor(LessonAssignment $assignment, SchedulingInput $input): array;
}

