<?php

declare(strict_types=1);

namespace Roostar\Modules\Students\Controllers;

use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Audit\AuditLogger;
use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Notifications\NotificationBag;
use Roostar\Core\Security\Csrf;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\PasswordService;
use Roostar\Modules\RosterData\Services\RosterDataCsvService;
use Roostar\Modules\Schools\Repositories\SchoolRepository;
use Roostar\Modules\Students\Repositories\StudentRepository;
use Roostar\Modules\Users\UserCreator;

final class StudentController
{
    public function index(): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return $this->forbidden();
        }

        $db = Connection::get();
        $encryptor = new Encryptor($_ENV['ENCRYPTION_KEY'] ?? '');
        $repository = new StudentRepository($db, $encryptor);

        return Response::html(AppView::render('students/index', [
            'activePage' => 'leerlingen',
            'pageTitle' => 'Leerlingen',
            'csrfToken' => Csrf::token(),
            'schools' => (new SchoolRepository($db, $encryptor))->accessibleFor($user),
            'classes' => $repository->classesFor($user),
            'electiveSubjectsByClass' => $repository->electiveSubjectsByClass($user),
            'students' => $repository->listFor($user),
        ]));
    }

    public function store(Request $request): Response
    {
        return $this->handle($request, function (StudentRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('name');
            $email = mb_strtolower($request->string('email'));
            $password = (string) $request->input('password', '');

            if ($name === '' || $email === '' || $password === '') {
                throw new \InvalidArgumentException('Vul naam, e-mail en wachtwoord in.');
            }

            if (strlen($password) < 8) {
                throw new \InvalidArgumentException('Het wachtwoord moet minimaal 8 tekens zijn.');
            }

            $db = Connection::get();
            $creator = new UserCreator($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

            if ($creator->emailExists($email)) {
                throw new \InvalidArgumentException('Er bestaat al een gebruiker met dit e-mailadres.');
            }

            $studentId = $creator->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'leerling',
                'school_id' => $schoolId,
                'scholengroep_id' => null,
            ]);

            $grants = new PermissionGrantRepository($db);
            foreach (RoleDefaults::basePermissions('leerling') as $permission) {
                $grants->grant($studentId, $permission, 'school', $schoolId);
            }

            $electiveSubjectIds = $request->input('elective_subject_ids', []);
            $repository->syncProfile(
                $studentId,
                $schoolId,
                $request->string('klas_id') ?: null,
                $request->string('leerlingnummer'),
                is_array($electiveSubjectIds) ? $electiveSubjectIds : [],
            );
            NotificationBag::success('Leerling is aangemaakt.');
        }, 'students.created');
    }

    public function update(Request $request): Response
    {
        return $this->handle($request, function (StudentRepository $repository, string $schoolId) use ($request): void {
            $studentId = $request->string('student_id');
            $name = $request->string('name');
            $email = mb_strtolower($request->string('email'));

            if ($studentId === '') {
                throw new \InvalidArgumentException('Kies eerst een leerling.');
            }

            if ($name === '' || $email === '') {
                throw new \InvalidArgumentException('Vul naam en e-mail in.');
            }

            $repository->updateStudent($studentId, $schoolId, $name, $email, $request->string('active') === '1');
            $electiveSubjectIds = $request->input('elective_subject_ids', []);
            $repository->syncProfile(
                $studentId,
                $schoolId,
                $request->string('klas_id') ?: null,
                $request->string('leerlingnummer'),
                is_array($electiveSubjectIds) ? $electiveSubjectIds : [],
            );
            NotificationBag::success('Leerling is bijgewerkt.');
        }, 'students.updated');
    }

    public function delete(Request $request): Response
    {
        return $this->handle($request, function (StudentRepository $repository, string $schoolId) use ($request): void {
            $studentId = $request->string('student_id');

            if ($studentId === '') {
                throw new \InvalidArgumentException('Kies eerst een leerling.');
            }

            $repository->deactivate($studentId, $schoolId);
            NotificationBag::success('Leerling is verwijderd uit actieve lijsten.');
        }, 'students.deleted');
    }

    public function export(Request $request): Response
    {
        $user = AuthSession::userContext();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return $this->forbidden();
        }

        $schoolId = $request->string('school_id');
        if ($schoolId === '' || !$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE, 'school', $schoolId)) {
            return $this->forbidden();
        }

        $repository = new StudentRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $students = array_values(array_filter($repository->listFor($user), static fn (array $student): bool => (string) ($student['school_id'] ?? '') === $schoolId));
        $headers = ['naam', 'leerlingnummer', 'email', 'wachtwoord', 'klas', 'keuzevakken', 'active'];
        $rows = array_map(static fn (array $student): array => [
            'naam' => (string) $student['naam'],
            'leerlingnummer' => (string) ($student['leerlingnummer'] ?? ''),
            'email' => (string) $student['email'],
            'wachtwoord' => '',
            'klas' => (string) ($student['klas_naam'] ?? ''),
            'keuzevakken' => implode(';', array_map(static fn (array $subject): string => (string) ($subject['code'] ?: $subject['naam']), $student['electives'] ?? [])),
            'active' => !empty($student['active']) ? '1' : '0',
        ], $students);

        return Response::csv((new RosterDataCsvService())->body($headers, $rows), 'leerlingen.csv');
    }

    public function import(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/leerlingen');
        }

        $schoolId = $request->string('school_id');
        if ($schoolId === '' || !$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE, 'school', $schoolId)) {
            NotificationBag::error('Je mag geen leerlingen beheren voor deze school.');
            return Response::redirect('/leerlingen');
        }

        $db = Connection::get();
        $repository = new StudentRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $creator = new UserCreator($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $grants = new PermissionGrantRepository($db);
        $passwords = new PasswordService($db);
        $csv = new RosterDataCsvService();

        try {
            $rows = $csv->uploadedRows('csv_file');
            $count = 0;

            foreach ($rows as $row) {
                $name = $csv->value($row, ['naam', 'name']);
                $email = mb_strtolower($csv->value($row, ['email', 'e-mail']));
                $password = $csv->value($row, ['wachtwoord', 'password']);

                if ($name === '' || $email === '') {
                    continue;
                }

                if ($password !== '' && strlen($password) < 8) {
                    continue;
                }

                $studentId = $repository->studentIdByEmailForSchool($schoolId, $email);
                if ($studentId === null) {
                    if ($creator->emailExists($email)) {
                        continue;
                    }

                    $generatedPassword = $password === '';
                    $password = $generatedPassword ? 'RoostarV2!' : $password;
                    $studentId = $creator->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => $password,
                        'role' => 'leerling',
                        'school_id' => $schoolId,
                        'scholengroep_id' => null,
                    ]);

                    if ($generatedPassword) {
                        $passwords->setTemporaryPassword($studentId, $password);
                    }

                    foreach (RoleDefaults::basePermissions('leerling') as $permission) {
                        $grants->grant($studentId, $permission, 'school', $schoolId);
                    }
                }

                $classId = $repository->classIdByName($schoolId, $csv->value($row, ['klas', 'class']));
                $electiveSubjectIds = $classId
                    ? $repository->electiveSubjectIdsByCodes($classId, $csv->list($csv->value($row, ['keuzevakken', 'keuze_vakken', 'electives', 'elective_subjects'])))
                    : [];

                $repository->updateStudent($studentId, $schoolId, $name, $email, $csv->value($row, ['active', 'actief']) !== '0');
                $repository->syncProfile(
                    $studentId,
                    $schoolId,
                    $classId,
                    $csv->value($row, ['leerlingnummer', 'studentnummer', 'student_number']),
                    $electiveSubjectIds,
                );
                $count++;
            }

            NotificationBag::success('Leerlingen import klaar: ' . $count . ' toegevoegd/bijgewerkt.');
            (new AuditLogger($db))->record('students.csv_import', $user->id, 'school', $schoolId, ['count' => $count], (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'));
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable $error) {
            NotificationBag::error('Leerlingen import is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect('/leerlingen');
    }

    private function handle(Request $request, callable $callback, string $auditAction): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/leerlingen');
        }

        $schoolId = $request->string('school_id');
        if ($schoolId === '' || !$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE, 'school', $schoolId)) {
            NotificationBag::error('Je mag geen leerlingen beheren voor deze school.');
            return Response::redirect('/leerlingen');
        }

        $db = Connection::get();
        $repository = new StudentRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        try {
            $callback($repository, $schoolId);
            (new AuditLogger($db))->record($auditAction, $user->id, 'school', $schoolId, [], (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'));
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable) {
            NotificationBag::error('Opslaan is niet gelukt. Controleer of de waarde nog niet bestaat.');
        }

        return Response::redirect('/leerlingen');
    }

    private function forbidden(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'leerlingen',
            'pageTitle' => 'Geen toegang',
            'moduleTitle' => 'Geen toegang',
            'moduleDescription' => 'Je hebt geen recht om leerlingen te beheren.',
        ]), 403);
    }
}
