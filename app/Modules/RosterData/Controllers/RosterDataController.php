<?php

declare(strict_types=1);

namespace Roostar\Modules\RosterData\Controllers;

use Roostar\Core\Access\PermissionRegistry;
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
            $repository->createProgram(
                $schoolId,
                $name,
                $request->string('code'),
                $request->string('niveau'),
                is_array($subjectIds) ? $subjectIds : [],
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
            $repository->updateProgram(
                $programId,
                $schoolId,
                $name,
                $request->string('code'),
                $request->string('niveau'),
                is_array($subjectIds) ? $subjectIds : [],
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
            $capacity = $request->string('capaciteit') !== '' ? (int) $request->string('capaciteit') : null;

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een lokaalnaam in.');
            }

            if ($capacity !== null && $capacity < 1) {
                throw new \InvalidArgumentException('Capaciteit moet minimaal 1 zijn.');
            }

            $repository->createRoom($schoolId, $name, $capacity);
            NotificationBag::success('Lokaal is aangemaakt.');
        }, 'roster_data.room_created');
    }

    public function updateRoom(Request $request): Response
    {
        return $this->store($request, '/stamdata?tab=lokalen', function (RosterDataRepository $repository, string $schoolId) use ($request): void {
            $roomId = $request->string('lokaal_id');
            $name = $request->string('naam');
            $capacity = $request->string('capaciteit') !== '' ? (int) $request->string('capaciteit') : null;

            if ($roomId === '') {
                throw new \InvalidArgumentException('Kies eerst een lokaal.');
            }

            if ($name === '') {
                throw new \InvalidArgumentException('Vul een lokaalnaam in.');
            }

            if ($capacity !== null && $capacity < 1) {
                throw new \InvalidArgumentException('Capaciteit moet minimaal 1 zijn.');
            }

            $repository->updateRoom($roomId, $schoolId, $name, $capacity, $request->string('active') === '1');
            NotificationBag::success('Lokaal is bijgewerkt.');
        }, 'roster_data.room_updated');
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
            'programs' => $repository->programsFor($user),
            'classes' => $repository->classesFor($user),
            'subjects' => $repository->subjectsFor($user),
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
