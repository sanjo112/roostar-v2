<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters;

use Roostar\Core\Auth\UserContext;

final class RosterPolicy
{
    public static function canGenerate(UserContext $user, string $schoolId): bool
    {
        return $user->hasPermission('roster.generate', 'school', $schoolId);
    }

    public static function canView(UserContext $user, string $schoolId): bool
    {
        if ($user->schoolId === $schoolId && $user->hasPermission('roster.view', 'school', $schoolId)) {
            return true;
        }

        return $user->hasPermission('roster.view', 'school', $schoolId);
    }
}

