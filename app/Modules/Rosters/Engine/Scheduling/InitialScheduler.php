<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Scheduling;

use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;

interface InitialScheduler
{
    public function createInitialSchedule(SchedulingInput $input): Schedule;
}

