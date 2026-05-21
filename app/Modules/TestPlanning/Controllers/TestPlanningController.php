<?php

declare(strict_types=1);

namespace Roostar\Modules\TestPlanning\Controllers;

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
use Roostar\Modules\Schools\Repositories\SchoolRepository;
use Roostar\Modules\TestPlanning\Repositories\TestPlanningRepository;

final class TestPlanningController
{
    public function index(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->allowed($user)) {
            return $this->forbidden();
        }

        $db = Connection::get();
        $encryptor = new Encryptor($_ENV['ENCRYPTION_KEY'] ?? '');
        $repository = new TestPlanningRepository($db, $encryptor);
        $schoolYears = $repository->schoolYearsFor($user);
        $selectedSchoolYearId = $request->string('schooljaar_id');
        if ($selectedSchoolYearId === '' && $schoolYears !== []) {
            $selectedSchoolYearId = (string) $schoolYears[0]['id'];
        }

        $testWeeks = $repository->testWeeksFor($user, $selectedSchoolYearId ?: null);
        $selectedTestWeekId = $request->string('toetsweek_id');
        if ($selectedTestWeekId === '' && $testWeeks !== []) {
            $selectedTestWeekId = (string) $testWeeks[0]['id'];
        }

        return Response::html(AppView::render('test-planning/index', [
            'activePage' => 'toetsweken',
            'pageTitle' => 'Toetsplanning',
            'csrfToken' => Csrf::token(),
            'schools' => (new SchoolRepository($db, $encryptor))->accessibleFor($user),
            'schoolYears' => $schoolYears,
            'selectedSchoolYearId' => $selectedSchoolYearId,
            'periods' => $repository->periodsForSchoolYear($user, $selectedSchoolYearId ?: null),
            'testWeeks' => $testWeeks,
            'selectedTestWeekId' => $selectedTestWeekId,
            'tests' => $repository->testsFor($user, $selectedTestWeekId ?: null),
            ...$repository->metaFor($user),
        ]));
    }

    public function storeTestWeek(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository, string $userId) use ($request): string {
            $schoolId = $request->string('school_id');
            $schoolYearId = $request->string('schooljaar_id');
            $name = $request->string('naam');
            $week = (int) $request->string('week_nummer');
            $lessonPercentage = (int) ($request->string('les_percentage') !== '' ? $request->string('les_percentage') : '50');
            $lessonsPerDay = $request->string('lesuren_per_dag') !== '' ? (int) $request->string('lesuren_per_dag') : null;

            if ($schoolId === '' || $schoolYearId === '' || $name === '') {
                throw new \InvalidArgumentException('Kies schooljaar en vul een naam in.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $id = $repository->createTestWeek($user, $schoolId, $schoolYearId, $name, $week, $lessonPercentage, $request->string('verkort_rooster') === '1', $lessonsPerDay);
            $this->audit('test_week.created', $userId, $id, $request);
            NotificationBag::success('Toetsweek is aangemaakt.');

            return $this->redirectPath($schoolYearId, $id);
        });
    }

    public function updateTestWeek(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository, string $userId) use ($request): string {
            $id = $request->string('toetsweek_id');
            $schoolYearId = $request->string('schooljaar_id');
            $name = $request->string('naam');
            $lessonPercentage = (int) ($request->string('les_percentage') !== '' ? $request->string('les_percentage') : '50');
            $lessonsPerDay = $request->string('lesuren_per_dag') !== '' ? (int) $request->string('lesuren_per_dag') : null;

            if ($id === '' || $name === '') {
                throw new \InvalidArgumentException('Kies een toetsweek en vul een naam in.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->updateTestWeek($user, $id, $name, $lessonPercentage, $request->string('verkort_rooster') === '1', $lessonsPerDay, $request->string('active') === '1');
            $this->audit('test_week.updated', $userId, $id, $request);
            NotificationBag::success('Toetsweek is bijgewerkt.');

            return $this->redirectPath($schoolYearId, $id);
        });
    }

    public function deleteTestWeek(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository, string $userId) use ($request): string {
            $id = $request->string('toetsweek_id');
            $schoolYearId = $request->string('schooljaar_id');

            if ($id === '') {
                throw new \InvalidArgumentException('Kies een toetsweek.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->deleteTestWeek($user, $id);
            $this->audit('test_week.deleted', $userId, $id, $request);
            NotificationBag::success('Toetsweek is verwijderd.');

            return $this->redirectPath($schoolYearId, null);
        });
    }

    public function saveTest(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository, string $userId) use ($request): string {
            $id = $request->string('toets_id') ?: null;
            $testWeekId = $request->string('toetsweek_id');
            $schoolYearId = $request->string('schooljaar_id');
            $subjectId = $request->string('vak_id');
            $programId = $request->string('opleiding_id') ?: null;
            $name = $request->string('naam');
            $date = $request->string('datum') ?: null;
            $slot = $request->string('tijdslot');
            $duration = (int) ($request->string('duur_minuten') !== '' ? $request->string('duur_minuten') : '50');
            $roomId = $request->string('lokaal_id') ?: null;
            $surveillanceCount = (int) ($request->string('aantal_surveillance') !== '' ? $request->string('aantal_surveillance') : '1');

            if ($testWeekId === '' || $subjectId === '' || $name === '' || $slot === '') {
                throw new \InvalidArgumentException('Kies toetsweek, vak, naam en tijdslot.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $savedId = $repository->saveTest($user, $id, $testWeekId, $subjectId, $programId, $name, $date, $slot, $duration, $roomId, $surveillanceCount);
            $teacherIds = $request->input('leraar_ids', []);
            $repository->saveSurveillance($user, $savedId, is_array($teacherIds) ? $teacherIds : []);
            $this->audit($id ? 'test.updated' : 'test.created', $userId, $savedId, $request);
            NotificationBag::success('Toets is opgeslagen.');

            return $this->redirectPath($schoolYearId, $testWeekId);
        });
    }

    public function deleteTest(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository, string $userId) use ($request): string {
            $id = $request->string('toets_id');
            $testWeekId = $request->string('toetsweek_id');
            $schoolYearId = $request->string('schooljaar_id');

            if ($id === '') {
                throw new \InvalidArgumentException('Kies een toets.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->deleteTest($user, $id);
            $this->audit('test.deleted', $userId, $id, $request);
            NotificationBag::success('Toets is verwijderd.');

            return $this->redirectPath($schoolYearId, $testWeekId);
        });
    }

    public function saveSurveillance(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository) use ($request): string {
            $teacherIds = $request->input('leraar_ids', []);
            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->saveSurveillance($user, $request->string('toets_id'), is_array($teacherIds) ? $teacherIds : []);
            NotificationBag::success('Surveillance is opgeslagen.');

            return $this->redirectPath($request->string('schooljaar_id'), $request->string('toetsweek_id'));
        });
    }

    public function proposeSurveillance(Request $request): Response
    {
        return $this->storeAction($request, function (TestPlanningRepository $repository) use ($request): string {
            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $proposal = $repository->proposeSurveillance($user, $request->string('toets_id'));
            NotificationBag::success(count($proposal) . ' surveillant(en) voorgesteld.');

            return $this->redirectPath($request->string('schooljaar_id'), $request->string('toetsweek_id'));
        });
    }

    private function storeAction(Request $request, callable $callback): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->allowed($user)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/toetsweken');
        }

        $repository = new TestPlanningRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        try {
            return Response::redirect($callback($repository, $user->id));
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable $error) {
            NotificationBag::error('Toetsplanning opslaan is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect('/toetsweken');
    }

    private function redirectPath(string $schoolYearId, ?string $testWeekId): string
    {
        $query = [];
        if ($schoolYearId !== '') {
            $query['schooljaar_id'] = $schoolYearId;
        }
        if ($testWeekId !== null && $testWeekId !== '') {
            $query['toetsweek_id'] = $testWeekId;
        }

        return '/toetsweken' . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    private function allowed(object $user): bool
    {
        return $user->hasPermission(PermissionRegistry::TEST_PLANNING_MANAGE)
            || $user->hasPermission(PermissionRegistry::SCHOOL_MANAGE);
    }

    private function audit(string $action, string $userId, string $entityId, Request $request): void
    {
        (new AuditLogger(Connection::get()))->record(
            $action,
            $userId,
            'test_planning',
            $entityId,
            [],
            (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'),
        );
    }

    private function forbidden(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'toetsweken',
            'pageTitle' => 'Geen toegang',
            'moduleTitle' => 'Geen toegang',
            'moduleDescription' => 'Je hebt geen recht om toetsplanning te beheren.',
        ]), 403);
    }
}
