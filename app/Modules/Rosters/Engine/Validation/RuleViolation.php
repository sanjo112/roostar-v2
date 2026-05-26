<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Validation;

final class RuleViolation
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $severity,
        public readonly string $message,
        public readonly int $penalty = 0,
        public readonly array $context = [],
    ) {
    }
}

