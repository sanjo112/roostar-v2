<?php

declare(strict_types=1);

namespace Roostar\Modules\RosterData\Controllers;

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
use Roostar\Modules\RosterData\Repositories\RosterDataRepository;
use Roostar\Modules\Schools\Repositories\SchoolRepository;
use Roostar\Modules\Users\UserCreator;

final class RosterDataController
{
    private const TABS = [
        'schooljaren' => 'Schooljaren',
        'klassen' => 'Klassen',
        'vakken' => 'Vakken',
        'lokalen' => 'Lokalen',
        'opleidingen' => 'Opleidingen',
        'leraren' => 'Leraren',
    ];

    public function masterData(Request $request): Response
    {
        $tab = $request->string('tab', 'vakken');
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'vakken';

        return $this->renderMasterData($tab);
    }

    public function schoolYears(): Response
    {
        return Response::redirect('/stamdata?tab=schooljaren');
    }

    public function classes(): Response
    {
        return Response::redirect('/stamdata?tab=klassen');
    }

    public function education(): Response
    {
        return Response::redirect('/stamdata?tab=opleidingen');
    }

    public function teachers(): Response
    {
        return Response::redirect('/stamdata?tab=leraren');
    }

    public function storeSchoolYear(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=schooljaren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('naam');
            $startDate = $request->string('startdatum');
            $endDate = $request->string('einddatum');

            if ($name === '' || $startDate === '' || $endDate === '') {
                throw new \InvalidArgumentException('Vul naam, startdatum en einddatum in.');
            }

            if ($endDate < $startDate) {
                throw new \InvalidArgumentException('De einddatum moet na de startdatum liggen.');
            }

            $repository->createSchoolYear($schoolId, $name, $startDate, $endDate);
            NotificationBag::success('Schooljaar is aangemaakt.');
        }, 'roster_data.school_year_created');
    }

    public function updateSchoolYear(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=schooljaren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $schoolYearId = $request->string('schooljaar_id');
            $name = $request->string('naam');
            $startDate = $request->string('startdatum');
            $endDate = $request->string('einddatum');

            if ($schoolYearId === '') {
                throw new \InvalidArgumentException('Kies eerst een schooljaar.');
            }

            if ($name === '' || $startDate === '' || $endDate === '') {
                throw new \InvalidArgumentException('Vul naam, startdatum en einddatum in.');
            }

            if ($endDate < $startDate) {
                throw new \InvalidArgumentException('De einddatum moet na de startdatum liggen.');
            }

            $repository->updateSchoolYear($schoolYearId, $schoolId, $name, $startDate, $endDate, $request->string('active') === '1');
            NotificationBag::success('Schooljaar is bijgewerkt.');
        }, 'roster_data.school_year_updated');
    }

    public function storePeriod(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=schooljaren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $schoolYearId = $request->string('schooljaar_id');
            $name = $request->string('naam');
            [$weekFromYear, $weekFrom] = $this->weekSelection($request, 'week_van');
            [$weekToYear, $weekTo] = $this->weekSelection($request, 'week_tot');

            if ($schoolYearId === '') {
                throw new \InvalidArgumentException('Kies eerst een schooljaar.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een periodenaam in.');
            }

            $repository->createPeriod($schoolYearId, $schoolId, $name, $weekFrom, $weekTo, $weekFromYear, $weekToYear);
            NotificationBag::success('Periode is toegevoegd.');
        }, 'roster_data.period_created');
    }

    public function updatePeriod(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=schooljaren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $periodId = $request->string('periode_id');
            $schoolYearId = $request->string('schooljaar_id');
            $name = $request->string('naam');
            [$weekFromYear, $weekFrom] = $this->weekSelection($request, 'week_van');
            [$weekToYear, $weekTo] = $this->weekSelection($request, 'week_tot');

            if ($periodId === '' || $schoolYearId === '') {
                throw new \InvalidArgumentException('Kies eerst een periode.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een periodenaam in.');
            }

            $repository->updatePeriod($periodId, $schoolYearId, $schoolId, $name, $weekFrom, $weekTo, $request->string('active') === '1', $weekFromYear, $weekToYear);
            NotificationBag::success('Periode is bijgewerkt.');
        }, 'roster_data.period_updated');
    }

    public function deletePeriod(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=schooljaren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $periodId = $request->string('periode_id');
            $schoolYearId = $request->string('schooljaar_id');

            if ($periodId === '' || $schoolYearId === '') {
                throw new \InvalidArgumentException('Kies eerst een periode.');
            }

            $repository->deletePeriod($periodId, $schoolYearId, $schoolId);
            NotificationBag::success('Periode is verwijderd.');
        }, 'roster_data.period_deleted');
    }

    public function storeClass(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=klassen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('naam');
            $schoolYearId = $request->string('schooljaar_id') ?: null;
            $programId = $request->string('opleiding_id') ?: null;
            $yearLevel = $request->string('leerjaar') !== '' ? (int) $request->string('leerjaar') : null;

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een klasnaam in.');
            }

            if ($yearLevel !== null && ($yearLevel < 1 || $yearLevel > 8)) {
                throw new \InvalidArgumentException('Leerjaar moet tussen 1 en 8 liggen.');
            }

            $repository->createClass($schoolId, $name, $schoolYearId, $programId, $yearLevel);
            NotificationBag::success('Klas is aangemaakt.');
        }, 'roster_data.class_created');
    }

    public function updateClass(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=klassen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $classId = $request->string('klas_id');
            $name = $request->string('naam');
            $schoolYearId = $request->string('schooljaar_id') ?: null;
            $programId = $request->string('opleiding_id') ?: null;
            $yearLevel = $request->string('leerjaar') !== '' ? (int) $request->string('leerjaar') : null;

            if ($classId === '') {
                throw new \InvalidArgumentException('Kies eerst een klas.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een klasnaam in.');
            }

            if ($yearLevel !== null && ($yearLevel < 1 || $yearLevel > 8)) {
                throw new \InvalidArgumentException('Leerjaar moet tussen 1 en 8 liggen.');
            }

            $repository->updateClass($classId, $schoolId, $name, $schoolYearId, $programId, $yearLevel, $request->string('active') === '1');
            NotificationBag::success('Klas is bijgewerkt.');
        }, 'roster_data.class_updated');
    }

    public function storeProgram(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=opleidingen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('naam');

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een opleidingsnaam in.');
            }

            $subjectIds = $request->input('subject_ids', []);
            $subjectHours = $request->input('subject_hours', []);
            $electiveSubjectIds = $request->input('elective_subject_ids', []);
            $repository->createProgram(
                $schoolId,
                $name,
                $request->string('code'),
                $request->string('niveau'),
                is_array($subjectIds) ? $subjectIds : [],
                is_array($electiveSubjectIds) ? $electiveSubjectIds : [],
                is_array($subjectHours) ? $subjectHours : [],
            );
            NotificationBag::success('Opleiding is aangemaakt.');
        }, 'roster_data.program_created');
    }

    public function updateProgram(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=opleidingen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $programId = $request->string('opleiding_id');
            $name = $request->string('naam');

            if ($programId === '') {
                throw new \InvalidArgumentException('Kies eerst een opleiding.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een opleidingsnaam in.');
            }

            $subjectIds = $request->input('subject_ids', []);
            $subjectHours = $request->input('subject_hours', []);
            $electiveSubjectIds = $request->input('elective_subject_ids', []);
            $repository->updateProgram(
                $programId,
                $schoolId,
                $name,
                $request->string('code'),
                $request->string('niveau'),
                is_array($subjectIds) ? $subjectIds : [],
                is_array($electiveSubjectIds) ? $electiveSubjectIds : [],
                is_array($subjectHours) ? $subjectHours : [],
                $request->string('active') === '1',
            );
            NotificationBag::success('Opleiding is bijgewerkt.');
        }, 'roster_data.program_updated');
    }

    public function storeSubject(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=vakken', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('naam');

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een vaknaam in.');
            }

            $repository->createSubject($schoolId, $name, $request->string('code'));
            NotificationBag::success('Vak is aangemaakt.');
        }, 'roster_data.subject_created');
    }

    public function updateSubject(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=vakken', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $subjectId = $request->string('vak_id');
            $name = $request->string('naam');

            if ($subjectId === '') {
                throw new \InvalidArgumentException('Kies eerst een vak.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een vaknaam in.');
            }

            $repository->updateSubject($subjectId, $schoolId, $name, $request->string('code'), $request->string('active') === '1');
            NotificationBag::success('Vak is bijgewerkt.');
        }, 'roster_data.subject_updated');
    }

    public function storeRoom(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=lokalen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('naam');
            $locationId = $request->string('locatie_id');
            $capacity = $request->string('capaciteit') !== '' ? (int) $request->string('capaciteit') : null;

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een lokaalnaam in.');
            }

            if ($locationId === '') {
                throw new \InvalidArgumentException('Kies een locatie.');
            }

            if ($capacity !== null && $capacity < 1) {
                throw new \InvalidArgumentException('Capaciteit moet minimaal 1 zijn.');
            }

            $subjectIds = $request->input('subject_ids', []);
            $repository->createRoom(
                $schoolId,
                $locationId,
                $name,
                $capacity,
                $this->arrayInput($request, 'available_slots'),
                is_array($subjectIds) ? $subjectIds : [],
            );
            NotificationBag::success('Lokaal is aangemaakt.');
        }, 'roster_data.room_created');
    }

    public function updateRoom(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=lokalen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $roomId = $request->string('lokaal_id');
            $name = $request->string('naam');
            $locationId = $request->string('locatie_id');
            $capacity = $request->string('capaciteit') !== '' ? (int) $request->string('capaciteit') : null;

            if ($roomId === '') {
                throw new \InvalidArgumentException('Kies eerst een lokaal.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een lokaalnaam in.');
            }

            if ($locationId === '') {
                throw new \InvalidArgumentException('Kies een locatie.');
            }

            if ($capacity !== null && $capacity < 1) {
                throw new \InvalidArgumentException('Capaciteit moet minimaal 1 zijn.');
            }

            $subjectIds = $request->input('subject_ids', []);
            $repository->updateRoom(
                $roomId,
                $schoolId,
                $locationId,
                $name,
                $capacity,
                $this->arrayInput($request, 'available_slots'),
                is_array($subjectIds) ? $subjectIds : [],
                $request->string('active') === '1',
            );
            NotificationBag::success('Lokaal is bijgewerkt.');
        }, 'roster_data.room_updated');
    }

    public function storeLocation(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=lokalen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $name = $request->string('naam');

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een locatienaam in.');
            }

            $repository->createLocation($schoolId, $name, $request->string('extern') === '1');
            NotificationBag::success('Locatie is aangemaakt.');
        }, 'roster_data.location_created');
    }

    public function storeTeacher(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=leraren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
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

            $teacherId = $creator->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'leraar',
                'school_id' => $schoolId,
                'scholengroep_id' => null,
            ]);

            $grants = new PermissionGrantRepository($db);
            foreach (RoleDefaults::basePermissions('leraar') as $permission) {
                $grants->grant($teacherId, $permission, 'school', $schoolId);
            }

            $availableSlots = $this->arrayInput($request, 'available_slots');
            $repository->syncTeacherProfile(
                $teacherId,
                $schoolId,
                $this->hoursPerWeekFromSlots($availableSlots),
                $this->hoursPerDayFromSlots($availableSlots),
                $availableSlots,
                $this->arrayInput($request, 'subject_ids'),
            );
            NotificationBag::success('Leraar is aangemaakt.');
        }, 'roster_data.teacher_created');
    }

    public function updateTeacher(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=leraren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $teacherId = $request->string('teacher_id');
            $name = $request->string('name');
            $email = mb_strtolower($request->string('email'));

            if ($teacherId === '') {
                throw new \InvalidArgumentException('Kies eerst een leraar.');
            }

            if ($name === '' || $email === '') {
                throw new \InvalidArgumentException('Vul naam en e-mail in.');
            }

            $availableSlots = $this->arrayInput($request, 'available_slots');
            $repository->updateTeacher($teacherId, $schoolId, $name, $email, $request->string('active') === '1');
            $repository->syncTeacherProfile(
                $teacherId,
                $schoolId,
                $this->hoursPerWeekFromSlots($availableSlots),
                $this->hoursPerDayFromSlots($availableSlots),
                $availableSlots,
                $this->arrayInput($request, 'subject_ids'),
            );
            NotificationBag::success('Leraar is bijgewerkt.');
        }, 'roster_data.teacher_updated');
    }

    public function deleteTeacher(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=leraren', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $teacherId = $request->string('teacher_id');

            if ($teacherId === '') {
                throw new \InvalidArgumentException('Kies eerst een leraar.');
            }

            $repository->deactivateTeacher($teacherId, $schoolId);
            NotificationBag::success('Leraar is verwijderd uit actieve roosters.');
        }, 'roster_data.teacher_deleted');
    }

    private function renderMasterData(string $tab): Response
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
        $repository = new RosterDataRepository($db, $encryptor);
        $schools = (new SchoolRepository($db, $encryptor))->accessibleFor($user);

        return Response::html(AppView::render('roster-data/stamdata', [
            'activePage' => 'stamdata',
            'pageTitle' => 'Stamdata',
            'activeTab' => $tab,
            'tabs' => self::TABS,
            'csrfToken' => Csrf::token(),
            'schools' => $schools,
            'schoolYears' => $repository->schoolYearsFor($user),
            'periods' => $repository->periodsFor($user),
            'programs' => $repository->programsFor($user),
            'classes' => $repository->classesFor($user),
            'subjects' => $repository->subjectsFor($user),
            'locations' => $repository->locationsFor($user),
            'rooms' => $repository->roomsFor($user),
            'teachers' => $repository->teachersFor($user),
        ]));
    }

    private function store(Request $request, string $redirectPath, callable $callback, string $auditAction): Response
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
            return Response::redirect($redirectPath);
        }

        $schoolId = $request->string('school_id');

        if ($schoolId === '' || !$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE, 'school', $schoolId)) {
            NotificationBag::error('Je mag geen roosterdata beheren voor deze school.');
            return Response::redirect($redirectPath);
        }

        $db = Connection::get();
        $repository = new RosterDataRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        try {
            $callback($repository, $schoolId);
            (new AuditLogger($db))->record($auditAction, $user->id, 'school', $schoolId, [], (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'));
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable) {
            NotificationBag::error('Opslaan is niet gelukt. Controleer of de waarde nog niet bestaat.');
        }

        return Response::redirect($redirectPath);
    }

    private function arrayInput(Request $request, string $key): array
    {
        $value = $request->input($key, []);

        return is_array($value) ? $value : [];
    }

    private function weekSelection(Request $request, string $key): array
    {
        $selected = $request->string($key . '_key');
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $selected, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [
            $request->string($key . '_jaar') !== '' ? (int) $request->string($key . '_jaar') : null,
            $request->string($key) !== '' ? (int) $request->string($key) : 0,
        ];
    }

    private function hoursPerWeekFromSlots(array $availableSlots): int
    {
        return max(1, min(45, count($this->validAvailabilitySlots($availableSlots))));
    }

    private function hoursPerDayFromSlots(array $availableSlots): int
    {
        $counts = ['ma' => 0, 'di' => 0, 'wo' => 0, 'do' => 0, 'vr' => 0];
        foreach ($this->validAvailabilitySlots($availableSlots) as $slot) {
            [$day] = explode('-', $slot, 2);
            if (array_key_exists($day, $counts)) {
                $counts[$day]++;
            }
        }

        return max(1, min(9, max($counts)));
    }

    private function validAvailabilitySlots(array $availableSlots): array
    {
        $allowed = [];
        foreach (['ma', 'di', 'wo', 'do', 'vr'] as $day) {
            for ($period = 1; $period <= 9; $period++) {
                $allowed[] = $day . '-' . $period;
            }
        }

        return array_values(array_intersect(
            $allowed,
            array_values(array_unique(array_filter($availableSlots, static fn (mixed $slot): bool => is_string($slot) && $slot !== ''))),
        ));
    }

    private function forbidden(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'stamdata',
            'pageTitle' => 'Geen toegang',
            'moduleTitle' => 'Geen toegang',
            'moduleDescription' => 'Je hebt geen recht om roosterbasisdata te beheren.',
        ]), 403);
    }
}
