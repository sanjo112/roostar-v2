<?php

declare(strict_types=1);

use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Database\Connection;
use Roostar\Core\Security\Encryptor;
use Roostar\Modules\Schools\SchoolCreator;
use Roostar\Modules\Users\UserCreator;

$app = require __DIR__ . '/../bootstrap/app.php';

if (($app['config']['app']['env'] ?? 'production') !== 'local') {
    fwrite(STDERR, "dev-seed may only run when APP_ENV=local." . PHP_EOL);
    exit(1);
}

Connection::configure($app['config']['database']);

$db = Connection::get();
$encryptor = new Encryptor($app['config']['security']['encryption_key']);
$schools = new SchoolCreator($db, $encryptor);
$users = new UserCreator($db, $encryptor);
$grants = new PermissionGrantRepository($db);

$scholengroepId = $schools->createScholengroep('Roostar Demo Scholengroep');
$schoolId = $schools->createSchool($scholengroepId, 'Roostar Demo School');

$adminId = $users->create([
    'name' => 'Roostar Admin',
    'email' => 'admin@roostar.local',
    'password' => 'RoostarV2!',
    'role' => 'school_admin',
    'school_id' => $schoolId,
    'scholengroep_id' => null,
]);

foreach (RoleDefaults::basePermissions('school_admin') as $permission) {
    $grants->grant($adminId, $permission, 'school', $schoolId);
}

$plannerId = $users->create([
    'name' => 'Roostar Planner',
    'email' => 'planner@roostar.local',
    'password' => 'RoostarV2!',
    'role' => 'rooster_medewerker',
    'school_id' => $schoolId,
    'scholengroep_id' => null,
]);

foreach (RoleDefaults::basePermissions('rooster_medewerker') as $permission) {
    $grants->grant($plannerId, $permission, 'school', $schoolId);
}

$sgAdminId = $users->create([
    'name' => 'Scholengroep Admin',
    'email' => 'sg@roostar.local',
    'password' => 'RoostarV2!',
    'role' => 'sg_admin',
    'school_id' => null,
    'scholengroep_id' => $scholengroepId,
]);

foreach (RoleDefaults::basePermissions('sg_admin') as $permission) {
    $grants->grant($sgAdminId, $permission, 'school', $schoolId);
}

echo "Seeded local V2 data." . PHP_EOL;
echo "School ID: {$schoolId}" . PHP_EOL;
echo "Login school admin: admin@roostar.local / RoostarV2!" . PHP_EOL;
echo "Login planner: planner@roostar.local / RoostarV2!" . PHP_EOL;
echo "Login SG admin: sg@roostar.local / RoostarV2!" . PHP_EOL;
echo "Note: SG admin has no roster.generate grant." . PHP_EOL;

