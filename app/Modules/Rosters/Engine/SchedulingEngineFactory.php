<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine;

use Roostar\Modules\Rosters\Engine\Explanation\ScheduleExplainer;
use Roostar\Modules\Rosters\Engine\Optimization\ImprovePreferencesOptimizer;
use Roostar\Modules\Rosters\Engine\Optimization\ImproveSpreadOptimizer;
use Roostar\Modules\Rosters\Engine\Optimization\ReduceGapsOptimizer;
use Roostar\Modules\Rosters\Engine\Optimization\ReduceMovesOptimizer;
use Roostar\Modules\Rosters\Engine\Rules\Hard\AllLessonsScheduledRule;
use Roostar\Modules\Rosters\Engine\Rules\Hard\AvailabilityRule;
use Roostar\Modules\Rosters\Engine\Rules\Hard\BlockHoursAllowedRule;
use Roostar\Modules\Rosters\Engine\Rules\Hard\ClassGroupNotDoubleBookedRule;
use Roostar\Modules\Rosters\Engine\Rules\Hard\RoomNotDoubleBookedRule;
use Roostar\Modules\Rosters\Engine\Rules\Hard\TeacherNotDoubleBookedRule;
use Roostar\Modules\Rosters\Engine\Rules\Soft\ClassMorningStartRule;
use Roostar\Modules\Rosters\Engine\Rules\Soft\StudentGapRule;
use Roostar\Modules\Rosters\Engine\Rules\Soft\SubjectSpreadRule;
use Roostar\Modules\Rosters\Engine\Rules\Soft\TeacherMoveRule;
use Roostar\Modules\Rosters\Engine\Rules\Soft\TeacherRoomPreferenceRule;
use Roostar\Modules\Rosters\Engine\Scheduling\GreedyInitialScheduler;
use Roostar\Modules\Rosters\Engine\Scoring\ScheduleScorer;
use Roostar\Modules\Rosters\Engine\Validation\ScheduleValidator;

final class SchedulingEngineFactory
{
    public static function default(): SchedulingEngine
    {
        $validator = new ScheduleValidator([
            new AllLessonsScheduledRule(),
            new TeacherNotDoubleBookedRule(),
            new ClassGroupNotDoubleBookedRule(),
            new RoomNotDoubleBookedRule(),
            new AvailabilityRule(),
            new BlockHoursAllowedRule(),
            new StudentGapRule(),
            new ClassMorningStartRule(),
            new TeacherMoveRule(),
            new SubjectSpreadRule(),
            new TeacherRoomPreferenceRule(),
        ]);

        return new SchedulingEngine(
            new GreedyInitialScheduler(),
            new ScheduleScorer($validator),
            new ScheduleExplainer(),
            [
                new ReduceMovesOptimizer(80),
                new ReduceGapsOptimizer(120),
                new ImproveSpreadOptimizer(120),
                new ImprovePreferencesOptimizer(80),
            ],
        );
    }
}
