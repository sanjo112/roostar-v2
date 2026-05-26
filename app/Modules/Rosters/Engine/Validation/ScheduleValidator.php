<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Validation;

use Roostar\Modules\Rosters\Engine\Contracts\Rule;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;

final class ScheduleValidator
{
    /**
     * @param Rule[] $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    public function validate(Schedule $schedule, SchedulingInput $input): ValidationResult
    {
        $result = new ValidationResult();

        foreach ($this->rules as $rule) {
            $result = $result->merge($rule->validate($schedule, $input));
        }

        return $result;
    }

    public function hardRules(): array
    {
        return array_values(array_filter($this->rules, static fn (Rule $rule): bool => $rule->severity() === 'hard'));
    }

    public function softRules(): array
    {
        return array_values(array_filter($this->rules, static fn (Rule $rule): bool => $rule->severity() === 'soft'));
    }
}

