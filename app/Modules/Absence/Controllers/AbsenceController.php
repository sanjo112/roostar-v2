<?php

declare(strict_types=1);

namespace Roostar\Modules\Absence\Controllers;

use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Audit\AuditLogger;
use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Notifications\NotificationBag;
use Roostar\Core\Security\Csrf;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\View\AppView;
use Roostar\Modules\Absence\Repositories\AbsenceRepository;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Schools\Repositories\SchoolRepository;

final class AbsenceController
{
    public function index(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::ABSENCE_MANAGE) && !$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return $this->forbidden();
        }

        $db = Connection::get();
        $encryptor = new Encryptor($_ENV['ENCRYPTION_KEY'] ?? '');
        $repository = new AbsenceRepository($db, $encryptor);
        $selectedAbsenceId = $request->string('id');
        $absences = $repository->activeAbsences($user);

        if ($selectedAbsenceId === '' && $absences !== []) {
            $selectedAbsenceId = (string) $absences[0]['id'];
        }

        $impact = $selectedAbsenceId !== ''
            ? $repository->impactFor($user, $selectedAbsenceId)
            : ['absence' => null, 'lessons' => [], 'summary' => ['lessen' => 0, 'opgevangen' => 0, 'uitgeroosterd' => 0, 'open' => 0, 'klassen' => 0, 'dagen' => 0]];

        return Response::html(AppView::render('absence/index', [
            'activePage' => 'ziekte',
            'pageTitle' => 'Ziekte',
            'csrfToken' => Csrf::token(),
            'schools' => (new SchoolRepository($db, $encryptor))->accessibleFor($user),
            'teachers' => $repository->teachersFor($user),
            'absences' => $absences,
            'selectedAbsenceId' => $selectedAbsenceId,
            'impact' => $impact,
            'today' => date('Y-m-d'),
        ]));
    }

    public function store(Request $request): Response
    {
        return $this->storeAction($request, function (AbsenceRepository $repository, string $userId) use ($request): string {
            $schoolId = $request->string('school_id');
            $teacherId = $request->string('leraar_id');
            $dateFrom = $request->string('datum_van');
            $dateTo = $request->string('datum_tot') ?: null;
            $note = $request->string('opmerking') ?: null;

            if ($schoolId === '' || $teacherId === '' || $dateFrom === '') {
                throw new \InvalidArgumentException('Kies school, leraar en startdatum.');
            }

            if ($dateTo !== null && $dateTo < $dateFrom) {
                throw new \InvalidArgumentException('Einddatum moet na de startdatum liggen.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $id = $repository->create($user, $schoolId, $teacherId, $dateFrom, $dateTo, $note);
            $this->audit('absence.created', $userId, $id, $request);
            NotificationBag::success('Ziekmelding is aangemaakt.');

            return '/ziekte?id=' . rawurlencode($id);
        });
    }

    public function replace(Request $request): Response
    {
        return $this->storeAction($request, function (AbsenceRepository $repository, string $userId) use ($request): string {
            $absenceId = $request->string('ziekte_id');
            $lessonId = $request->string('les_id');
            $date = $request->string('datum');
            $replacementTeacherId = $request->string('vervanger_id');

            if ($absenceId === '' || $lessonId === '' || $date === '' || $replacementTeacherId === '') {
                throw new \InvalidArgumentException('Kies een les en vervanger.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->replaceLesson($user, $absenceId, $lessonId, $date, $replacementTeacherId);
            $this->audit('absence.lesson_replaced', $userId, $absenceId, $request);
            NotificationBag::success('Vervanger is opgeslagen.');

            return '/ziekte?id=' . rawurlencode($absenceId);
        });
    }

    public function replaceRange(Request $request): Response
    {
        return $this->storeAction($request, function (AbsenceRepository $repository, string $userId) use ($request): string {
            $absenceId = $request->string('ziekte_id');
            $replacementTeacherId = $request->string('vervanger_id');
            $dateFrom = $request->string('datum_van');
            $dateTo = $request->string('datum_tot') ?: null;
            $hours = $request->input('uren', []);

            if ($absenceId === '' || $replacementTeacherId === '' || $dateFrom === '') {
                throw new \InvalidArgumentException('Kies ziekmelding, vervanger en startdatum.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $result = $repository->replaceRange($user, $absenceId, $replacementTeacherId, $dateFrom, $dateTo, is_array($hours) ? $hours : []);
            $this->audit('absence.range_replaced', $userId, $absenceId, $request);
            NotificationBag::success($result['applied'] . ' les(sen) vervangen.');

            if ($result['skipped'] !== []) {
                NotificationBag::warning(count($result['skipped']) . ' les(sen) overgeslagen door beschikbaarheid of conflict.');
            }

            return '/ziekte?id=' . rawurlencode($absenceId);
        });
    }

    public function cancelLesson(Request $request): Response
    {
        return $this->storeAction($request, function (AbsenceRepository $repository, string $userId) use ($request): string {
            $absenceId = $request->string('ziekte_id');
            $lessonId = $request->string('les_id');
            $date = $request->string('datum');

            if ($absenceId === '' || $lessonId === '' || $date === '') {
                throw new \InvalidArgumentException('Kies een les.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->cancelLesson($user, $absenceId, $lessonId, $date);
            $this->audit('absence.lesson_cancelled', $userId, $absenceId, $request);
            NotificationBag::success('Les is uitgeroosterd.');

            return '/ziekte?id=' . rawurlencode($absenceId);
        });
    }

    public function clearReplacement(Request $request): Response
    {
        return $this->storeAction($request, function (AbsenceRepository $repository, string $userId) use ($request): string {
            $absenceId = $request->string('ziekte_id');
            $lessonId = $request->string('les_id');
            $date = $request->string('datum');

            if ($absenceId === '' || $lessonId === '' || $date === '') {
                throw new \InvalidArgumentException('Kies een les.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->clearReplacement($user, $absenceId, $lessonId, $date);
            $this->audit('absence.replacement_cleared', $userId, $absenceId, $request);
            NotificationBag::success('Vervanger is verwijderd.');

            return '/ziekte?id=' . rawurlencode($absenceId);
        });
    }

    public function resolve(Request $request): Response
    {
        return $this->storeAction($request, function (AbsenceRepository $repository, string $userId) use ($request): string {
            $absenceId = $request->string('ziekte_id');
            $dateTo = $request->string('datum_tot', date('Y-m-d'));

            if ($absenceId === '') {
                throw new \InvalidArgumentException('Kies een ziekmelding.');
            }

            $user = AuthSession::userContext();
            if (!$user) {
                throw new \InvalidArgumentException('Niet ingelogd.');
            }

            $repository->resolve($user, $absenceId, $dateTo);
            $this->audit('absence.resolved', $userId, $absenceId, $request);
            NotificationBag::success('Ziekmelding is afgesloten.');

            return '/ziekte';
        });
    }

    private function storeAction(Request $request, callable $callback): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::ABSENCE_MANAGE) && !$user->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/ziekte');
        }

        $db = Connection::get();
        $repository = new AbsenceRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        try {
            return Response::redirect($callback($repository, $user->id));
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable $error) {
            NotificationBag::error('Ziekte opslaan is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect('/ziekte');
    }

    private function audit(string $action, string $userId, string $entityId, Request $request): void
    {
        (new AuditLogger(Connection::get()))->record(
            $action,
            $userId,
            'absence',
            $entityId,
            [],
            (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'),
        );
    }

    private function forbidden(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'ziekte',
            'pageTitle' => 'Geen toegang',
            'moduleTitle' => 'Geen toegang',
            'moduleDescription' => 'Je hebt geen recht om ziekte en vervanging te beheren.',
        ]), 403);
    }
}
