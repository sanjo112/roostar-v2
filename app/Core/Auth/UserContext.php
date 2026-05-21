<?php

declare(strict_types=1);

namespace Roostar\Core\Auth;

final class UserContext
{
    public function __construct(
        public readonly string $id,
        public readonly string $role,
        public readonly ?string $scholengroepId,
        public readonly ?string $schoolId,
        public readonly bool $forcePasswordChange = false,
        public readonly array $permissions = [],
    ) {
    }

    public function hasPermission(string $permission, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        foreach ($this->permissions as $grant) {
            if (($grant['permission'] ?? null) !== $permission) {
                continue;
            }

            if ($scopeType === null) {
                return true;
            }

            if (($grant['scope_type'] ?? null) === $scopeType && ($grant['scope_id'] ?? null) === $scopeId) {
                return true;
            }
        }

        return false;
    }
}
