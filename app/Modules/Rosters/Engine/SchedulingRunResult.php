<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine;

use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Scoring\Score;

final class SchedulingRunResult
{
    public function __construct(
        public readonly Schedule $schedule,
        public readonly Score $score,
        public readonly array $steps,
        public readonly array $explanations,
    ) {
    }
}

