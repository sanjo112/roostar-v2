<?php

declare(strict_types=1);

use Roostar\Core\Auth\UserContext;
use Roostar\Modules\Rosters\RosterPolicy;

$schoolId = 'school-1';

$sgAdminWithoutGrant = new UserContext(
    id: 'user-1',
    role: 'sg_admin',
    scholengroepId: 'sg-1',
    schoolId: null,
    forcePasswordChange: false,
    permissions: [],
);

assertFalse(
    RosterPolicy::canGenerate($sgAdminWithoutGrant, $schoolId),
    'sg_admin mag niet impliciet roosters genereren.',
);

$rosterEmployeeWithGrant = new UserContext(
    id: 'user-2',
    role: 'rooster_medewerker',
    scholengroepId: null,
    schoolId: $schoolId,
    forcePasswordChange: false,
    permissions: [
        ['permission' => 'roster.generate', 'scope_type' => 'school', 'scope_id' => $schoolId],
    ],
);

assertTrue(
    RosterPolicy::canGenerate($rosterEmployeeWithGrant, $schoolId),
    'rooster_medewerker met school-scope grant mag roosters genereren.',
);

$otherSchoolGrant = new UserContext(
    id: 'user-3',
    role: 'rooster_medewerker',
    scholengroepId: null,
    schoolId: 'school-2',
    forcePasswordChange: false,
    permissions: [
        ['permission' => 'roster.generate', 'scope_type' => 'school', 'scope_id' => 'school-2'],
    ],
);

assertFalse(
    RosterPolicy::canGenerate($otherSchoolGrant, $schoolId),
    'roster.generate is schoolgebonden en mag niet doorsijpelen naar andere scholen.',
);

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}
