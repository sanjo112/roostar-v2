<?php

declare(strict_types=1);

namespace Roostar\Modules\Navigation;

use Roostar\Core\Access\RoleDefaults;

final class NavigationBuilder
{
    public static function forRole(string $role, string $activePage): array
    {
        $allowed = RoleDefaults::pageAccess($role);

        $groups = [
            'Hoofdmenu' => [
                new NavigationItem('roostar-admin', 'Roostar Admin', '/roostar-admin', 'platform', 'HQ'),
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
}
