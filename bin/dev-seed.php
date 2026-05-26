<?php

declare(strict_types=1);

use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Database\Connection;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;
use Roostar\Modules\RosterData\Repositories\RosterDataRepository;
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
$rosterData = new RosterDataRepository($db, $encryptor);

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
$grants->grant($adminId, PermissionRegistry::ROSTER_GENERATE, 'school', $schoolId);
$grants->grant($adminId, PermissionRegistry::ROSTER_EDIT, 'school', $schoolId);

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
    'email' => 'sg-admin@roostar.local',
    'password' => 'RoostarV2!',
    'role' => 'sg_admin',
    'school_id' => null,
    'scholengroep_id' => $scholengroepId,
]);

foreach (RoleDefaults::basePermissions('sg_admin') as $permission) {
    $grants->grant($sgAdminId, $permission, 'school', $schoolId);
}

$teacherSeeds = [
    ['name' => 'Anouk de Vries', 'email' => 'anouk@roostar.local'],
    ['name' => 'Bram Jansen', 'email' => 'bram@roostar.local'],
    ['name' => 'Cem Kaya', 'email' => 'cem@roostar.local'],
    ['name' => 'Daphne Smit', 'email' => 'daphne@roostar.local'],
];

$teacherIds = [];
foreach ($teacherSeeds as $teacher) {
    $teacherId = $users->create([
        'name' => $teacher['name'],
        'email' => $teacher['email'],
        'password' => 'RoostarV2!',
        'role' => 'leraar',
        'school_id' => $schoolId,
        'scholengroep_id' => null,
    ]);

    foreach (RoleDefaults::basePermissions('leraar') as $permission) {
        $grants->grant($teacherId, $permission, 'school', $schoolId);
    }

    $teacherIds[$teacher['email']] = $teacherId;
}

$studentSeeds = [
    ['name' => 'Mila Bakker', 'email' => 'mila@roostar.local', 'number' => 'L001'],
    ['name' => 'Noah Visser', 'email' => 'noah@roostar.local', 'number' => 'L002'],
    ['name' => 'Yara Smit', 'email' => 'yara@roostar.local', 'number' => 'L003'],
    ['name' => 'Finn de Jong', 'email' => 'finn@roostar.local', 'number' => 'L004'],
    ['name' => 'Sara Meijer', 'email' => 'sara@roostar.local', 'number' => 'L005'],
    ['name' => 'Levi Bos', 'email' => 'levi@roostar.local', 'number' => 'L006'],
];
$studentIds = [];

foreach ($studentSeeds as $student) {
    $studentId = $users->create([
        'name' => $student['name'],
        'email' => $student['email'],
        'password' => 'RoostarV2!',
        'role' => 'leerling',
        'school_id' => $schoolId,
        'scholengroep_id' => null,
    ]);

    foreach (RoleDefaults::basePermissions('leerling') as $permission) {
        $grants->grant($studentId, $permission, 'school', $schoolId);
    }

    $studentIds[$student['email']] = ['id' => $studentId, 'number' => $student['number']];
}

$findEncryptedId = static function (string $table, string $name) use ($db, $schoolId): ?string {
    $stmt = $db->prepare("
        SELECT id
        FROM {$table}
        WHERE school_id = :school_id
          AND naam_search_hash = :naam_search_hash
        LIMIT 1
    ");
    $stmt->execute([
        'school_id' => $schoolId,
        'naam_search_hash' => Str::searchHash($name),
    ]);
    $id = $stmt->fetchColumn();

    return is_string($id) ? $id : null;
};

$subjectIds = [];
foreach ([
    ['Wiskunde', 'WIS'],
    ['Nederlands', 'NED'],
    ['Engels', 'ENG'],
    ['Biologie', 'BIO'],
    ['Scheikunde', 'SCH'],
    ['Natuurkunde', 'NAT'],
] as [$name, $code]) {
    $subjectId = $findEncryptedId('vakken', $name);

    if ($subjectId === null) {
        $rosterData->createSubject($schoolId, $name, $code);
        $subjectId = $findEncryptedId('vakken', $name);
    }

    if ($subjectId !== null) {
        $subjectIds[$code] = $subjectId;
    }
}

$teacherSubjects = [
    'anouk@roostar.local' => ['NED', 'ENG'],
    'bram@roostar.local' => ['WIS', 'NAT'],
    'cem@roostar.local' => ['BIO', 'SCH'],
    'daphne@roostar.local' => ['ENG', 'NED', 'WIS'],
];

$teacherSubjectStmt = $db->prepare("
    INSERT IGNORE INTO leraar_vakken (user_id, vak_id, created_at)
    VALUES (:user_id, :vak_id, NOW())
");
$teacherProfileStmt = $db->prepare("
    INSERT INTO leraar_profielen (user_id, max_uren_per_week, max_uren_per_dag, beschikbaarheid_json, created_at, updated_at)
    VALUES (:user_id, :max_uren_per_week, :max_uren_per_dag, :beschikbaarheid_json, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        max_uren_per_week = VALUES(max_uren_per_week),
        max_uren_per_dag = VALUES(max_uren_per_dag),
        beschikbaarheid_json = VALUES(beschikbaarheid_json),
        updated_at = NOW()
");
$defaultTeacherSlots = [];
foreach (['ma', 'di', 'wo', 'do', 'vr'] as $dayKey) {
    foreach (range(1, 8) as $period) {
        $defaultTeacherSlots[] = $dayKey . '-' . $period;
    }
}

foreach ($teacherSubjects as $email => $codes) {
    if (!empty($teacherIds[$email])) {
        $teacherProfileStmt->execute([
            'user_id' => $teacherIds[$email],
            'max_uren_per_week' => count($defaultTeacherSlots),
            'max_uren_per_dag' => 8,
            'beschikbaarheid_json' => json_encode($defaultTeacherSlots, JSON_THROW_ON_ERROR),
        ]);
    }

    foreach ($codes as $code) {
        if (!empty($teacherIds[$email]) && !empty($subjectIds[$code])) {
            $teacherSubjectStmt->execute([
                'user_id' => $teacherIds[$email],
                'vak_id' => $subjectIds[$code],
            ]);
        }
    }
}

$roomSubjects = [
    ['A101', 32, ['NED', 'ENG']],
    ['B204', 32, ['WIS', 'NAT']],
    ['Lab 1', 32, ['BIO', 'SCH', 'NAT']],
    ['Mediatheek', 30, ['NED', 'ENG']],
];

$mainLocationId = $findEncryptedId('locaties', 'Roostar Demo School');
if ($mainLocationId === null) {
    $rosterData->createLocation($schoolId, 'Roostar Demo School', false);
    $mainLocationId = $findEncryptedId('locaties', 'Roostar Demo School');
}

foreach ($roomSubjects as [$name, $capacity, $codes]) {
    $roomId = $findEncryptedId('lokalen', $name);
    $roomSubjectIds = array_values(array_filter(
        array_map(static fn (string $code): ?string => $subjectIds[$code] ?? null, $codes),
    ));

    if ($roomId === null) {
        $rosterData->createRoom($schoolId, (string) $mainLocationId, $name, $capacity, [], $roomSubjectIds);
    } elseif ($mainLocationId !== null) {
        $rosterData->updateRoom($roomId, $schoolId, (string) $mainLocationId, $name, $capacity, [], $roomSubjectIds, true);
    }
}

$stmt = $db->prepare("
    SELECT id
    FROM schooljaren
    WHERE school_id = :school_id
      AND naam = :naam
    LIMIT 1
");
$stmt->execute([
    'school_id' => $schoolId,
    'naam' => '2026-2027',
]);
$schoolYearId = $stmt->fetchColumn();

if (!is_string($schoolYearId)) {
    $rosterData->createSchoolYear($schoolId, '2026-2027', '2026-08-24', '2027-07-16');
    $stmt->execute([
        'school_id' => $schoolId,
        'naam' => '2026-2027',
    ]);
    $schoolYearId = $stmt->fetchColumn();
}

if (is_string($schoolYearId)) {
    $periodStmt = $db->prepare("
        INSERT IGNORE INTO schooljaar_periodes (id, schooljaar_id, naam, week_van, week_tot, active, created_at, updated_at)
        VALUES (:id, :schooljaar_id, :naam, :week_van, :week_tot, 1, NOW(), NOW())
    ");

    foreach ([
        ['Periode 1', 35, 43],
        ['Periode 2', 44, 51],
        ['Periode 3', 2, 10],
        ['Periode 4', 11, 20],
    ] as [$name, $weekFrom, $weekTo]) {
        $periodStmt->execute([
            'id' => Str::uuid(),
            'schooljaar_id' => $schoolYearId,
            'naam' => $name,
            'week_van' => $weekFrom,
            'week_tot' => $weekTo,
        ]);
    }
}

$programId = $findEncryptedId('opleidingen', 'Havo onderbouw');
$programSubjects = array_values(array_intersect_key($subjectIds, array_flip(['WIS', 'NED', 'ENG', 'BIO'])));
$programElectives = array_values(array_intersect_key($subjectIds, array_flip(['BIO'])));

if ($programId === null) {
    $rosterData->createProgram($schoolId, 'Havo onderbouw', 'HAVO-OB', 'Havo', $programSubjects, $programElectives);
    $programId = $findEncryptedId('opleidingen', 'Havo onderbouw');
} else {
    $rosterData->updateProgram($programId, $schoolId, 'Havo onderbouw', 'HAVO-OB', 'Havo', $programSubjects, $programElectives, [], true);
}

if (is_string($programId)) {
    $hoursByCode = ['WIS' => 4, 'NED' => 4, 'ENG' => 3, 'BIO' => 2];
    $hoursStmt = $db->prepare("
        UPDATE opleiding_vakken
        SET uren_per_week = :uren_per_week
        WHERE opleiding_id = :opleiding_id
          AND vak_id = :vak_id
    ");

    foreach ($hoursByCode as $code => $hours) {
        if (!empty($subjectIds[$code])) {
            $hoursStmt->execute([
                'uren_per_week' => $hours,
                'opleiding_id' => $programId,
                'vak_id' => $subjectIds[$code],
            ]);
        }
    }
}

if (is_string($schoolYearId) && is_string($programId)) {
    foreach ([
        ['H2A', 2],
        ['H2B', 2],
        ['H3A', 3],
    ] as [$name, $yearLevel]) {
        if ($findEncryptedId('klassen', $name) === null) {
            $rosterData->createClass($schoolId, $name, $schoolYearId, $programId, $yearLevel);
        }
    }
}

$classIds = array_values(array_filter([
    $findEncryptedId('klassen', 'H2A'),
    $findEncryptedId('klassen', 'H2B'),
    $findEncryptedId('klassen', 'H3A'),
]));
$studentProfileStmt = $db->prepare("
    INSERT INTO leerling_profielen (user_id, klas_id, leerlingnummer, created_at, updated_at)
    VALUES (:user_id, :klas_id, :leerlingnummer, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        klas_id = VALUES(klas_id),
        leerlingnummer = VALUES(leerlingnummer),
        updated_at = NOW()
");
$studentElectiveStmt = $db->prepare("
    INSERT IGNORE INTO leerling_keuzevakken (user_id, vak_id, created_at)
    VALUES (:user_id, :vak_id, NOW())
");
$studentIndex = 0;

foreach ($studentIds as $student) {
    $studentProfileStmt->execute([
        'user_id' => $student['id'],
        'klas_id' => $classIds[$studentIndex % max(1, count($classIds))] ?? null,
        'leerlingnummer' => $student['number'],
    ]);

    if (!empty($subjectIds['BIO']) && $studentIndex % 2 === 0) {
        $studentElectiveStmt->execute([
            'user_id' => $student['id'],
            'vak_id' => $subjectIds['BIO'],
        ]);
    }

    $studentIndex++;
}

if (is_string($schoolYearId) && !empty($subjectIds['NED'])) {
    $testWeekId = null;
    $stmt = $db->prepare("
        SELECT id
        FROM toetsweken
        WHERE school_id = :school_id
          AND schooljaar_id = :schooljaar_id
          AND week_nummer = 43
        LIMIT 1
    ");
    $stmt->execute(['school_id' => $schoolId, 'schooljaar_id' => $schoolYearId]);
    $existingTestWeekId = $stmt->fetchColumn();
    $periodId = null;
    $stmt = $db->prepare("
        SELECT id
        FROM schooljaar_periodes
        WHERE schooljaar_id = :schooljaar_id
          AND (
            (week_van <= week_tot AND 43 BETWEEN week_van AND week_tot)
            OR (week_van > week_tot AND (43 >= week_van OR 43 <= week_tot))
          )
        LIMIT 1
    ");
    $stmt->execute(['schooljaar_id' => $schoolYearId]);
    $foundPeriodId = $stmt->fetchColumn();
    if (is_string($foundPeriodId)) {
        $periodId = $foundPeriodId;
    }

    if (is_string($existingTestWeekId)) {
        $testWeekId = $existingTestWeekId;
        $stmt = $db->prepare("
            UPDATE toetsweken
            SET naam = 'Toetsweek 1',
                periode_id = :periode_id,
                les_percentage = 50,
                verkort_rooster = 1,
                lesuren_per_dag = 5,
                active = 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $testWeekId, 'periode_id' => $periodId]);
    } else {
        $testWeekId = Str::uuid();
        $stmt = $db->prepare("
            INSERT INTO toetsweken (
                id, school_id, schooljaar_id, periode_id, naam, week_nummer, les_percentage, verkort_rooster, lesuren_per_dag, active, created_at, updated_at
            ) VALUES (
                :id, :school_id, :schooljaar_id, :periode_id, 'Toetsweek 1', 43, 50, 1, 5, 1, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'id' => $testWeekId,
            'school_id' => $schoolId,
            'schooljaar_id' => $schoolYearId,
            'periode_id' => $periodId,
        ]);
    }

    $roomId = $findEncryptedId('lokalen', 'Mediatheek') ?? $findEncryptedId('lokalen', 'A101');
    $testId = null;
    $stmt = $db->prepare("SELECT id FROM toetsen WHERE toetsweek_id = :toetsweek_id AND naam = 'Leesvaardigheid Nederlands' LIMIT 1");
    $stmt->execute(['toetsweek_id' => $testWeekId]);
    $existingTestId = $stmt->fetchColumn();
    if (is_string($existingTestId)) {
        $testId = $existingTestId;
    } else {
        $testId = Str::uuid();
    }

    $stmt = $db->prepare("
        INSERT INTO toetsen (
            id, toetsweek_id, vak_id, opleiding_id, naam, datum, tijdslot, duur_minuten, lokaal_id, aantal_surveillance, created_at, updated_at
        ) VALUES (
            :id, :toetsweek_id, :vak_id, :opleiding_id, 'Leesvaardigheid Nederlands', '2026-10-22', 'do-2', 50, :lokaal_id, 1, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            vak_id = VALUES(vak_id),
            opleiding_id = VALUES(opleiding_id),
            datum = VALUES(datum),
            tijdslot = VALUES(tijdslot),
            duur_minuten = VALUES(duur_minuten),
            lokaal_id = VALUES(lokaal_id),
            aantal_surveillance = VALUES(aantal_surveillance),
            updated_at = NOW()
    ");
    $stmt->execute([
        'id' => $testId,
        'toetsweek_id' => $testWeekId,
        'vak_id' => $subjectIds['NED'],
        'opleiding_id' => $programId,
        'lokaal_id' => $roomId,
    ]);

    if (!empty($teacherIds['anouk@roostar.local'])) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO toets_surveillance (toets_id, leraar_id, voorstel, created_at)
            VALUES (:toets_id, :leraar_id, 0, NOW())
        ");
        $stmt->execute([
            'toets_id' => $testId,
            'leraar_id' => $teacherIds['anouk@roostar.local'],
        ]);
    }
}

echo "Seeded local V2 data." . PHP_EOL;
echo "School ID: {$schoolId}" . PHP_EOL;
echo "Seeded subjects, rooms, school year, program and classes." . PHP_EOL;
echo "Login school admin: admin@roostar.local / RoostarV2!" . PHP_EOL;
echo "Login planner: planner@roostar.local / RoostarV2!" . PHP_EOL;
echo "Login SG admin: sg-admin@roostar.local / RoostarV2!" . PHP_EOL;
echo "Note: SG admin has no roster.generate grant." . PHP_EOL;
