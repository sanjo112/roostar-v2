<?php

declare(strict_types=1);

use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;

$sgAdminPermissions = RoleDefaults::basePermissions('sg_admin');

assertNotContains(
    PermissionRegistry::ROSTER_GENERATE,
    $sgAdminPermissions,
    'sg_admin krijgt roster.generate niet als basisrecht.',
);

$rosterEmployeePermissions = RoleDefaults::basePermissions('rooster_medewerker');

assertContains(
    PermissionRegistry::ROSTER_GENERATE,
    $rosterEmployeePermissions,
    'rooster_medewerker krijgt roster.generate als basisrecht, later scoped naar school.',
);

function assertContains(string $needle, array $haystack, string $message): void
{
    if (!in_array($needle, $haystack, true)) {
        throw new RuntimeException($message);
    }
}

function assertNotContains(string $needle, array $haystack, string $message): void
{
    if (in_array($needle, $haystack, true)) {
        throw new RuntimeException($message);
    }
}

