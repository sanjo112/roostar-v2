<?php

declare(strict_types=1);

use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Database\Connection;
use Roostar\Core\Security\Encryptor;
use Roostar\Modules\Users\UserCreator;

$app = require __DIR__ . '/../bootstrap/app.php';

$options = getopt('', [
    'name:',
    'email:',
    'password:',
    'role:',
    'school-id::',
    'scholengroep-id::',
    'grant-school::',
]);

foreach (['name', 'email', 'password', 'role'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Missing --{$required}" . PHP_EOL);
        exit(1);
    }
}

Connection::configure($app['config']['database']);
$db = Connection::get();

$creator = new UserCreator(
    $db,
    new Encryptor($app['config']['security']['encryption_key']),
);

$userId = $creator->create([
    'name' => $options['name'],
    'email' => $options['email'],
    'password' => $options['password'],
    'role' => $options['role'],
    'school_id' => $options['school-id'] ?? null,
    'scholengroep_id' => $options['scholengroep-id'] ?? null,
]);

$grantSchoolId = $options['grant-school'] ?? $options['school-id'] ?? null;

if ($grantSchoolId) {
    $grants = new PermissionGrantRepository($db);

    foreach (RoleDefaults::basePermissions((string) $options['role']) as $permission) {
        $scopeType = $permission === PermissionRegistry::PLATFORM_MANAGE ? 'platform' : 'school';
        $scopeId = $scopeType === 'platform' ? 'platform' : (string) $grantSchoolId;
        $grants->grant($userId, $permission, $scopeType, $scopeId);
    }
}

echo "Created user: {$userId}" . PHP_EOL;

