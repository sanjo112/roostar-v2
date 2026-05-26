<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Validation;

final class ValidationResult
{
    /**
     * @param RuleViolation[] $violations
     */
    public function __construct(
        private readonly array $violations = [],
    ) {
    }

    public function isValid(): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->severity === 'hard') {
                return false;
            }
        }

        return true;
    }

    public function hardCount(): int
    {
        return count(array_filter($this->violations, static fn (RuleViolation $violation): bool => $violation->severity === 'hard'));
    }

    public function softCount(): int
    {
        return count(array_filter($this->violations, static fn (RuleViolation $violation): bool => $violation->severity === 'soft'));
    }

    public function penalty(): int
    {
        return array_sum(array_map(static fn (RuleViolation $violation): int => $violation->penalty, $this->violations));
    }

    /**
     * @return RuleViolation[]
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function merge(self $other): self
    {
        return new self([...$this->violations, ...$other->violations()]);
    }
}

