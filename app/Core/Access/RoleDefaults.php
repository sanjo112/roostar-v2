<?php

declare(strict_types=1);

namespace Roostar\Core\Access;

final class RoleDefaults
{
    public static function pageAccess(string $role): array
    {
        return match ($role) {
            'roostar_admin' => ['roostar-admin', 'rooster-queue', 'profiel'],
            'sg_admin' => ['rooster', 'stamdata', 'leerlingen', 'ziekte', 'toetsweken', 'stage', 'gebruikers', 'auditlog', 'settings', 'registratie', 'profiel'],
            'school_admin' => ['rooster', 'stamdata', 'leerlingen', 'ziekte', 'toetsweken', 'stage', 'gebruikers', 'auditlog', 'settings', 'registratie', 'profiel'],
            'afdelingsleider' => ['afdeling', 'leraren', 'settings', 'profiel'],
            'rooster_medewerker' => ['rooster', 'rooster-genereren', 'ziekte', 'toetsweken', 'profiel'],
            'leraar' => ['rooster', 'profiel'],
            'leerling' => ['rooster', 'profiel'],
            default => ['profiel'],
        };
    }

    public static function basePermissions(string $role): array
    {
        return match ($role) {
            'roostar_admin' => [PermissionRegistry::PLATFORM_MANAGE],
            'sg_admin' => [PermissionRegistry::SCHOOL_MANAGE, PermissionRegistry::USERS_MANAGE, PermissionRegistry::AUDIT_VIEW, PermissionRegistry::ROSTER_VIEW],
            'school_admin' => [PermissionRegistry::SCHOOL_MANAGE, PermissionRegistry::USERS_MANAGE, PermissionRegistry::AUDIT_VIEW, PermissionRegistry::ROSTER_VIEW],
            'afdelingsleider' => [PermissionRegistry::SCHOOL_MANAGE, PermissionRegistry::ROSTER_VIEW],
            'rooster_medewerker' => [PermissionRegistry::ROSTER_VIEW, PermissionRegistry::ROSTER_GENERATE, PermissionRegistry::ROSTER_EDIT, PermissionRegistry::ABSENCE_MANAGE, PermissionRegistry::TEST_PLANNING_MANAGE],
            'leraar', 'leerling' => [PermissionRegistry::ROSTER_VIEW],
            default => [],
        };
    }
}
