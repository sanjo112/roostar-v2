<?php

declare(strict_types=1);

namespace Roostar\Modules\Navigation;

use Roostar\Core\Auth\UserContext;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Access\PermissionRegistry;

final class NavigationBuilder
{
    public static function forRole(string $role, string $activePage): array
    {
        $allowed = RoleDefaults::pageAccess($role);

        return self::build($allowed, $activePage);
    }

    public static function forUser(UserContext $user, string $activePage): array
    {
        return self::build(self::pageAccessForPermissions($user), $activePage);
    }

    private static function build(array $allowed, string $activePage): array
    {

        $groups = [
            'Hoofdmenu' => [
                new NavigationItem('roostar-admin', 'Roostar Admin', '/roostar-admin', 'platform', 'HQ'),
                new NavigationItem('rooster-queue', 'Rooster queue', '/roostar-admin/queue', 'bolt'),
                new NavigationItem('rooster', 'Rooster', '/rooster', 'roster', 'v12'),
                new NavigationItem('rooster-genereren', 'Rooster genereren', '/roosters/genereren', 'bolt', 'AI'),
                new NavigationItem('ziekte', 'Ziekte', '/ziekte', 'heart'),
                new NavigationItem('toetsweken', 'Toetsplanning', '/toetsweken', 'document'),
                new NavigationItem('stage', 'Stage', '/stage', 'briefcase'),
            ],
            'Beheer' => [
                new NavigationItem('stamdata', 'Stamdata', '/stamdata', 'database'),
                new NavigationItem('leerlingen', 'Leerlingen', '/leerlingen', 'student'),
                new NavigationItem('gebruikers', 'Gebruikers', '/gebruikers', 'users'),
                new NavigationItem('auditlog', 'Auditlog', '/auditlog', 'audit'),
                new NavigationItem('settings', 'Instellingen', '/settings', 'settings'),
            ],
        ];

        return array_map(
            static fn (array $items): array => array_values(array_filter(
                array_map(
                    static fn (NavigationItem $item): NavigationItem => new NavigationItem(
                        $item->key,
                        $item->label,
                        $item->href,
                        $item->icon,
                        $item->badge,
                        $item->key === $activePage,
                    ),
                    $items,
                ),
                static fn (NavigationItem $item): bool => in_array($item->key, $allowed, true),
            )),
            $groups,
        );
    }

    private static function pageAccessForPermissions(UserContext $user): array
    {
        $allowed = ['profiel'];

        foreach ($user->permissions as $grant) {
            $permission = (string) ($grant['permission'] ?? '');

            $allowed = [
                ...$allowed,
                ...match ($permission) {
                    PermissionRegistry::PLATFORM_MANAGE => ['roostar-admin', 'rooster-queue'],
                    PermissionRegistry::SCHOOL_MANAGE => ['stamdata', 'leerlingen', 'settings'],
                    PermissionRegistry::USERS_MANAGE => ['gebruikers'],
                    PermissionRegistry::AUDIT_VIEW => ['auditlog'],
                    PermissionRegistry::ROSTER_VIEW => ['rooster'],
                    PermissionRegistry::ROSTER_GENERATE => ['rooster', 'rooster-genereren'],
                    PermissionRegistry::ROSTER_EDIT => ['rooster'],
                    PermissionRegistry::ABSENCE_MANAGE => ['ziekte'],
                    PermissionRegistry::TEST_PLANNING_MANAGE => ['toetsweken'],
                    PermissionRegistry::INTERNSHIP_MANAGE => ['stage'],
                    default => [],
                },
            ];
        }

        return array_values(array_unique($allowed));
    }
}
