<?php

declare(strict_types=1);

use Roostar\Core\Auth\UserContext;

$user = new UserContext(
    id: 'user-1',
    role: 'school_admin',
    scholengroepId: null,
    schoolId: 'school-1',
    forcePasswordChange: false,
    permissions: [
        ['permission' => 'users.manage', 'scope_type' => 'school', 'scope_id' => 'school-1'],
    ],
);

assertUserContextTrue(
    $user->hasPermission('users.manage'),
    'Een bestaande permission mag algemeen worden herkend voor navigatie en schermtoegang.',
);

assertUserContextTrue(
    $user->hasPermission('users.manage', 'school', 'school-1'),
    'Een permission met dezelfde school-scope moet geldig zijn.',
);

assertUserContextFalse(
    $user->hasPermission('users.manage', 'school', 'school-2'),
    'Een permission mag niet naar een andere school doorsijpelen.',
);

assertUserContextFalse(
    $user->hasPermission('roster.generate', 'school', 'school-1'),
    'Een ontbrekende permission mag niet impliciet worden toegekend.',
);

function assertUserContextTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertUserContextFalse(bool $condition, string $message): void
{
    assertUserContextTrue(!$condition, $message);
}
