<?php

declare(strict_types=1);

namespace Roostar\Core\Access;

final class PermissionRegistry
{
    public const PLATFORM_MANAGE = 'platform.manage';
    public const SCHOOL_MANAGE = 'school.manage';
    public const USERS_MANAGE = 'users.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const ROSTER_VIEW = 'roster.view';
    public const ROSTER_GENERATE = 'roster.generate';
    public const ROSTER_EDIT = 'roster.edit';
    public const ABSENCE_MANAGE = 'absence.manage';
    public const TEST_PLANNING_MANAGE = 'test_planning.manage';
    public const INTERNSHIP_MANAGE = 'internship.manage';

    public static function labels(): array
    {
        return [
            self::PLATFORM_MANAGE => 'Platform beheren',
            self::SCHOOL_MANAGE => 'School beheren',
            self::USERS_MANAGE => 'Gebruikers beheren',
            self::AUDIT_VIEW => 'Auditlog bekijken',
            self::ROSTER_VIEW => 'Rooster bekijken',
            self::ROSTER_GENERATE => 'Rooster genereren',
            self::ROSTER_EDIT => 'Rooster aanpassen',
            self::ABSENCE_MANAGE => 'Ziekte beheren',
            self::TEST_PLANNING_MANAGE => 'Toetsplanning beheren',
            self::INTERNSHIP_MANAGE => 'Stage beheren',
        ];
    }
}
