<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Contracts;

use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

interface Rule
{
    public function id(): string;

    public function severity(): string;

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult;
}

