<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Scoring;

use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class Score
{
    public function __construct(
        public readonly int $value,
        public readonly ValidationResult $validation,
    ) {
    }

    public function isBetterThan(self $other): bool
    {
        if ($this->validation->isValid() !== $other->validation->isValid()) {
            return $this->validation->isValid();
        }

        return $this->value > $other->value;
    }
}

