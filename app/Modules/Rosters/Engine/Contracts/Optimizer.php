<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Contracts;

use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Scoring\ScheduleScorer;

interface Optimizer
{
    public function name(): string;

    public function optimize(Schedule $schedule, SchedulingInput $input, ScheduleScorer $scorer): Schedule;
}

