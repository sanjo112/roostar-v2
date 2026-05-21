<?php

declare(strict_types=1);

use Roostar\Modules\Navigation\NavigationBuilder;

$sgNavigation = NavigationBuilder::forRole('sg_admin', 'dashboard');
$sgItems = flattenNavigationKeys($sgNavigation);

assertContainsNavigation('rooster', $sgItems, 'sg_admin mag rooster bekijken.');
assertContainsNavigation('auditlog', $sgItems, 'sg_admin ziet de auditlog voor securitybeheer.');
assertNotContainsNavigation('rooster-genereren', $sgItems, 'sg_admin ziet rooster genereren niet als basisnavigatie.');

$plannerNavigation = NavigationBuilder::forRole('rooster_medewerker', 'dashboard');
$plannerItems = flattenNavigationKeys($plannerNavigation);

assertContainsNavigation('rooster-genereren', $plannerItems, 'rooster_medewerker ziet rooster genereren.');
assertNotContainsNavigation('auditlog', $plannerItems, 'rooster_medewerker ziet de auditlog niet.');

function flattenNavigationKeys(array $groups): array
{
    $keys = [];

    foreach ($groups as $items) {
        foreach ($items as $item) {
            $keys[] = $item->key;
        }
    }

    return $keys;
}

function assertContainsNavigation(string $needle, array $haystack, string $message): void
{
    if (!in_array($needle, $haystack, true)) {
        throw new RuntimeException($message);
    }
}

function assertNotContainsNavigation(string $needle, array $haystack, string $message): void
{
    if (in_array($needle, $haystack, true)) {
        throw new RuntimeException($message);
    }
}
