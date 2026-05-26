<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Scoring;

use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\ScheduleValidator;

final class ScheduleScorer
{
    public function __construct(
        private readonly ScheduleValidator $validator,
    ) {
    }

    public function score(Schedule $schedule, SchedulingInput $input): Score
    {
        $validation = $this->validator->validate($schedule, $input);
        $base = $validation->isValid() ? 10000 : -100000;

        return new Score($base - $validation->penalty(), $validation);
    }
}

