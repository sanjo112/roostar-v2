<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters;

use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Http\View;
use Roostar\Core\Notifications\NotificationBag;
use Roostar\Core\Security\Csrf;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\RosterData\Repositories\RosterDataRepository;
use Roostar\Modules\Rosters\Engine\DemoSchedulingInputFactory;
use Roostar\Modules\Rosters\Engine\SchedulingEngineFactory;
use Roostar\Modules\Rosters\Repositories\RosterGenerationQueueRepository;
use Roostar\Modules\Rosters\Repositories\RosterGenerationRepository;
use Roostar\Modules\Rosters\Repositories\RosterWeekRepository;
use Roostar\Modules\Rosters\Services\RosterGenerationQueueStarter;
use Roostar\Modules\Rosters\Services\RosterGenerationQueueWorker;

final class RosterController
{
    public function index(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        $db = Connection::get();
        $repository = new RosterWeekRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $year = $request->string('jaar') !== '' ? (int) $request->string('jaar') : (int) date('o');
        $week = $request->string('week') !== '' ? (int) $request->string('week') : (int) date('W');
        $week = max(1, min(53, $week));

        return Response::html(AppView::render('rosters/week', [
            'activePage' => 'rooster',
            'pageTitle' => 'Rooster',
            'week' => $week,
            'year' => $year,
            'previousWeek' => max(1, $week - 1),
            'nextWeek' => min(53, $week + 1),
            'overview' => $repository->weekOverview($user, $week, $year),
            'periods' => $this->periodRanges(),
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function exportPdf(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        $repository = new RosterWeekRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $year = $request->string('jaar') !== '' ? (int) $request->string('jaar') : (int) date('o');
        $week = $request->string('week') !== '' ? (int) $request->string('week') : (int) date('W');
        $week = max(1, min(53, $week));

        return Response::html(View::render('pages/rosters/pdf', [
            'overview' => $repository->weekOverview($user, $week, $year),
            'periods' => $this->periodRanges(),
            'week' => $week,
            'year' => $year,
            'generatedAt' => date('d-m-Y H:i'),
        ]));
    }

    public function generate(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        $db = Connection::get();
        $encryptor = new Encryptor($_ENV['ENCRYPTION_KEY'] ?? '');
        $rosterData = new RosterDataRepository($db, $encryptor);
        $generationRepository = new RosterGenerationRepository($db, $encryptor);
        $queueRepository = new RosterGenerationQueueRepository($db, $encryptor);

        $schoolYears = $rosterData->schoolYearsFor($user);
        $periods = $rosterData->periodsFor($user);
        $classes = $rosterData->classesFor($user);
        $subjects = $rosterData->subjectsFor($user);
        $rooms = $rosterData->roomsFor($user);
        $teachers = $rosterData->teachersFor($user);

        $selectedSchoolYearId = $request->string('schooljaar_id', (string) ($schoolYears[0]['id'] ?? ''));
        $periodsForSchoolYear = array_values(array_filter(
            $periods,
            static fn (array $period): bool => $selectedSchoolYearId === '' || (string) ($period['schooljaar_id'] ?? '') === $selectedSchoolYearId,
        ));
        $selectedPeriodId = $request->string('periode_id', (string) ($periodsForSchoolYear[0]['id'] ?? ''));
        $selectedPeriod = $this->findById($periods, $selectedPeriodId);

        if ($selectedPeriod !== null) {
            $selectedSchoolYearId = (string) $selectedPeriod['schooljaar_id'];
            $periodsForSchoolYear = array_values(array_filter(
                $periods,
                static fn (array $period): bool => (string) ($period['schooljaar_id'] ?? '') === $selectedSchoolYearId,
            ));
        }

        $classesForSchoolYear = array_values(array_filter(
            $classes,
            static fn (array $class): bool => $selectedSchoolYearId === '' || (string) ($class['schooljaar_id'] ?? '') === $selectedSchoolYearId,
        ));
        $generated = null;

        if ($request->method !== 'POST' && $selectedPeriodId !== '') {
            $saved = $generationRepository->latestSavedRosterForPeriod($user, $selectedPeriodId);
            if ($saved !== null) {
                $generated = $this->presentGeneratedRoster(
                    $saved['constraints'],
                    $saved['result'],
                    $saved['validation'],
                    $saved['rosterIds'],
                );
                $generated['stored'] = true;
            }
        }

        if ($request->method === 'POST') {
            if (!Csrf::verify($request->string('_token'))) {
                NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            } elseif ($classesForSchoolYear === []) {
                NotificationBag::error('Er zijn geen actieve klassen in dit schooljaar.');
            } elseif ($selectedPeriodId === '') {
                NotificationBag::error('Maak eerst een periode aan voor dit schooljaar.');
            } else {
                try {
                    $constraints = $generationRepository->constraintsForPeriod($user, $selectedPeriodId);
                    $job = $queueRepository->enqueue(
                        (string) ($constraints['schoolId'] ?? ''),
                        (string) ($constraints['schoolYear']['id'] ?? $selectedSchoolYearId),
                        $selectedPeriodId,
                        $user->id,
                    );

                    if (($job['status'] ?? '') === 'queued') {
                        (new RosterGenerationQueueStarter(dirname(__DIR__, 3)))->startAndDeferFallback(
                            static function () use ($db, $encryptor): void {
                                (new RosterGenerationQueueWorker($db, $encryptor))->processAvailable(1);
                            },
                        );
                        NotificationBag::success('Rooster genereren is gestart.');
                    } else {
                        NotificationBag::warning('Er loopt al een roostergeneratie voor deze periode.');
                    }

                    return Response::redirect('/roosters/genereren?schooljaar_id=' . rawurlencode($selectedSchoolYearId) . '&periode_id=' . rawurlencode($selectedPeriodId));
                } catch (\Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    NotificationBag::error('Rooster genereren mislukt: ' . $e->getMessage());
                }
            }
        }

        $engineInput = DemoSchedulingInputFactory::create();

        return Response::html(AppView::render('rosters/generate', [
            'activePage' => 'rooster-genereren',
            'pageTitle' => 'Rooster genereren',
            'csrfToken' => Csrf::token(),
            'schoolYears' => $schoolYears,
            'periods' => $periods,
            'periodsForSchoolYear' => $periodsForSchoolYear,
            'classes' => $classes,
            'classesForSchoolYear' => $classesForSchoolYear,
            'subjects' => $subjects,
            'rooms' => $rooms,
            'teachers' => $teachers,
            'selectedSchoolYearId' => $selectedSchoolYearId,
            'selectedPeriodId' => $selectedPeriodId,
            'generated' => $generated,
            'generationJob' => $selectedPeriodId !== '' && !empty($classesForSchoolYear[0]['school_id'] ?? null)
                ? $this->runningGenerationJob($queueRepository->recentForPeriod((string) $classesForSchoolYear[0]['school_id'], $selectedPeriodId))
                : null,
            'feedbackJob' => $selectedPeriodId !== '' && !empty($classesForSchoolYear[0]['school_id'] ?? null)
                ? $this->feedbackGenerationJob($queueRepository->recentForPeriod((string) $classesForSchoolYear[0]['school_id'], $selectedPeriodId))
                : null,
            'readiness' => $this->readiness($classesForSchoolYear, $subjects, $rooms, $teachers, $periodsForSchoolYear),
            'engineInput' => $engineInput,
            'engineResult' => SchedulingEngineFactory::default()->run($engineInput),
        ]));
    }

    private function runningGenerationJob(array $jobs): ?array
    {
        foreach ($jobs as $job) {
            if ((string) ($job['status'] ?? '') === 'running') {
                return $job;
            }
        }

        return null;
    }

    private function feedbackGenerationJob(array $jobs): ?array
    {
        foreach ($jobs as $job) {
            $status = (string) ($job['status'] ?? '');

            if ($status === 'failed') {
                return $job;
            }

            if ($status === 'completed' && (
                (int) ($job['result_percent'] ?? 100) < 100
                || (int) ($job['hard_violations'] ?? 0) > 0
                || (int) ($job['soft_violations'] ?? 0) > 0
            )) {
                return $job;
            }
        }

        return null;
    }

    public function moveLesson(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::json(['success' => false, 'error' => 'Niet ingelogd.'], 401);
        }

        if (!Csrf::verify($request->string('_token'))) {
            return Response::json(['success' => false, 'error' => 'Je sessie is verlopen.'], 419);
        }

        $lessonId = $request->string('lesson_id');
        $periodIndex = $request->string('period_index') !== '' ? (int) $request->string('period_index') : -1;
        $dayIndex = $request->string('day_index') !== '' ? (int) $request->string('day_index') : -1;
        $days = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag'];
        $periods = $this->periodRanges();

        if ($lessonId === '' || !isset($days[$dayIndex], $periods[$periodIndex + 1])) {
            return Response::json(['success' => false, 'error' => 'Ongeldige verplaatsing.'], 422);
        }

        $db = Connection::get();
        $repository = new RosterGenerationRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $range = $periods[$periodIndex + 1];

        return Response::json($repository->moveLesson(
            $user,
            $lessonId,
            $days[$dayIndex],
            $periodIndex + 1,
            $range[0],
            $range[1],
        ));
    }

    public function moveWeekLesson(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::json(['success' => false, 'error' => 'Niet ingelogd.'], 401);
        }

        if (!Csrf::verify($request->string('_token'))) {
            return Response::json(['success' => false, 'error' => 'Je sessie is verlopen.'], 419);
        }

        $lessonId = $request->string('lesson_id');
        $week = $request->string('week') !== '' ? (int) $request->string('week') : 0;
        $periodIndex = $request->string('period_index') !== '' ? (int) $request->string('period_index') : -1;
        $dayIndex = $request->string('day_index') !== '' ? (int) $request->string('day_index') : -1;
        $days = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag'];
        $periods = $this->periodRanges();

        if ($lessonId === '' || $week < 1 || !isset($days[$dayIndex], $periods[$periodIndex + 1])) {
            return Response::json(['success' => false, 'error' => 'Ongeldige weekverplaatsing.'], 422);
        }

        $repository = new RosterWeekRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $range = $periods[$periodIndex + 1];

        return Response::json($repository->moveWeekLesson(
            $user,
            $lessonId,
            $week,
            $days[$dayIndex],
            $periodIndex + 1,
            $range[0],
            $range[1],
        ));
    }

    private function findById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    private function readiness(array $classes, array $subjects, array $rooms, array $teachers, array $periods): array
    {
        return [
            ['label' => 'Klassen', 'detail' => count($classes) . ' beschikbaar', 'ok' => count($classes) > 0],
            ['label' => 'Periodes', 'detail' => count($periods) . ' in schooljaar', 'ok' => count($periods) > 0],
            ['label' => 'Vakken', 'detail' => count($subjects) . ' beschikbaar', 'ok' => count($subjects) > 0],
            ['label' => 'Lokalen', 'detail' => count($rooms) . ' beschikbaar', 'ok' => count($rooms) > 0],
            ['label' => 'Leraren', 'detail' => count($teachers) . ' beschikbaar', 'ok' => count($teachers) > 0],
        ];
    }

    private function presentGeneratedRoster(array $constraints, array $result, array $validation, array $rosterIds): array
    {
        $days = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag'];
        $periods = $this->periodRanges();
        $colors = ['lesson-blue', 'lesson-green', 'lesson-coral', 'lesson-teal', 'lesson-yellow', 'lesson-purple'];
        $schedules = [];
        $views = [
            'class' => [],
            'teacher' => [],
            'room' => [],
        ];

        foreach ($constraints['classes'] ?? [] as $class) {
            $schedules[(string) $class['id']] = [
                'id' => $rosterIds[(string) $class['id']] ?? null,
                'class' => [
                    'naam' => $class['naam'],
                    'opleiding_naam' => $class['opleiding_naam'] ?? null,
                ],
                'days' => $days,
                'periods' => array_values($periods),
                'lessons' => [],
            ];
            $views['class'][(string) $class['id']] = [
                'id' => (string) $class['id'],
                'label' => (string) $class['naam'],
                'sub' => trim((string) ($class['opleiding_naam'] ?? '')),
                'lessons' => [],
            ];
        }

        foreach ($result['lessons'] ?? [] as $index => $lesson) {
            $classId = (string) $lesson['lessonGroup']['classId'];
            $teacherId = (string) $lesson['teacher']['id'];
            $roomId = (string) $lesson['room']['id'];
            $periodIndex = (int) $lesson['slot']['period'] - 1;
            $dayIndex = array_search((string) $lesson['slot']['day'], $days, true);

            if ($dayIndex === false || !isset($schedules[$classId])) {
                continue;
            }

            if (!isset($views['teacher'][$teacherId])) {
                $views['teacher'][$teacherId] = [
                    'id' => $teacherId,
                    'label' => (string) $lesson['teacher']['name'],
                    'sub' => 'Leraar',
                    'lessons' => [],
                ];
            }

            if (!isset($views['room'][$roomId])) {
                $views['room'][$roomId] = [
                    'id' => $roomId,
                    'label' => (string) $lesson['room']['name'],
                    'sub' => (string) $lesson['room']['capacity'] . ' plaatsen',
                    'lessons' => [],
                ];
            }

            $viewLesson = [
                'id' => (string) ($lesson['id'] ?? Str::uuid()),
                'classId' => $classId,
                'teacherId' => $teacherId,
                'roomId' => $roomId,
                'periodIndex' => $periodIndex,
                'dayIndex' => $dayIndex,
                'subject' => [
                    'naam' => $lesson['lessonGroup']['subject']['name'],
                    'code' => $lesson['lessonGroup']['subject']['code'],
                ],
                'class' => ['naam' => $lesson['lessonGroup']['className']],
                'teacher' => ['naam' => $lesson['teacher']['name']],
                'room' => [
                    'naam' => $lesson['room']['name'],
                    'capaciteit' => $lesson['room']['capacity'],
                ],
                'color' => $colors[$index % count($colors)],
            ];

            $schedules[$classId]['lessons'][$periodIndex][$dayIndex] = $viewLesson;
            $views['class'][$classId]['lessons'][$periodIndex][$dayIndex][] = $viewLesson;
            $views['teacher'][$teacherId]['lessons'][$periodIndex][$dayIndex][] = $viewLesson;
            $views['room'][$roomId]['lessons'][$periodIndex][$dayIndex][] = $viewLesson;
        }

        return [
            'ids' => $rosterIds,
            'class' => ['naam' => count($schedules) . ' klassen'],
            'period' => $constraints['period'] ?? null,
            'days' => $days,
            'periods' => array_values($periods),
            'views' => array_map(static fn (array $items): array => array_values($items), $views),
            'schedules' => array_values($schedules),
            'lessons' => array_values($schedules)[0]['lessons'] ?? [],
            'issues' => array_values(array_unique(array_merge($result['issues'] ?? [], $validation['errors'] ?? []))),
            'stats' => $result['stats'] ?? [],
            'valid' => (bool) ($validation['success'] ?? false),
        ];
    }

    private function periodRanges(): array
    {
        return [
            1 => ['08:30', '09:20'],
            2 => ['09:20', '10:10'],
            3 => ['10:25', '11:15'],
            4 => ['11:15', '12:05'],
            5 => ['12:45', '13:35'],
            6 => ['13:35', '14:25'],
            7 => ['14:25', '15:15'],
            8 => ['15:15', '16:05'],
            9 => ['16:05', '16:55'],
        ];
    }
}
