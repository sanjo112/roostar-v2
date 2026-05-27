<?php

declare(strict_types=1);

namespace Roostar\Modules\Platform\Controllers;

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
use Roostar\Modules\Platform\Repositories\PlatformAdminRepository;
use Roostar\Modules\Rosters\Repositories\RosterGenerationQueueRepository;
use Roostar\Modules\Rosters\Services\RosterGenerationQueueWorker;

final class PlatformAdminController
{
    public function index(): Response
    {
        $user = AuthSession::userContext();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::PLATFORM_MANAGE)) {
            return $this->forbidden();
        }

        $repository = new PlatformAdminRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        return Response::html(AppView::render('platform/index', [
            'activePage' => 'roostar-admin',
            'pageTitle' => 'Roostar Admin',
            'csrfToken' => Csrf::token(),
            'customers' => $repository->customers(),
            'groups' => $repository->groups(),
        ]));
    }

    public function queue(): Response
    {
        $user = AuthSession::userContext();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::PLATFORM_MANAGE)) {
            return $this->forbidden();
        }

        $queue = new RosterGenerationQueueRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        return Response::html(AppView::render('platform/queue', [
            'activePage' => 'rooster-queue',
            'pageTitle' => 'Rooster queue',
            'csrfToken' => Csrf::token(),
            'queueMaxConcurrent' => $queue->maxConcurrent(),
            'queueStats' => $queue->queueStats(),
            'queueJobs' => $queue->dashboardJobs(),
        ]));
    }

    public function updateQueueSettings(Request $request): Response
    {
        return $this->storeAction($request, function (PlatformAdminRepository $repository, string $userId) use ($request): void {
            $queue = new RosterGenerationQueueRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
            $queue->updateMaxConcurrent((int) $request->input('max_concurrent', 1));
            NotificationBag::success('Queue-capaciteit is bijgewerkt.');
        }, '/roostar-admin/queue');
    }

    public function processQueue(Request $request): Response
    {
        return $this->storeAction($request, function (PlatformAdminRepository $repository, string $userId) use ($request): void {
            $worker = new RosterGenerationQueueWorker(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
            $results = $worker->processAvailable(1);
            NotificationBag::success($results === []
                ? 'Geen roosterjobs om te verwerken.'
                : count($results) . ' roosterjob(s) verwerkt.');
        }, '/roostar-admin/queue');
    }

    public function storeCustomer(Request $request): Response
    {
        return $this->storeAction($request, function (PlatformAdminRepository $repository, string $userId) use ($request): void {
            $result = $repository->createCustomer(
                $request->string('scholengroep_naam'),
                $request->string('school_naam'),
                $request->string('admin_naam') ?: null,
                $request->string('admin_email') ?: null,
                (string) $request->input('admin_wachtwoord', ''),
            );

            $this->audit('platform.customer_created', $userId, (string) $result['school_id'], $request);
            NotificationBag::success('Klant is aangemaakt.');
        });
    }

    public function storeSchoolAdmin(Request $request): Response
    {
        return $this->storeAction($request, function (PlatformAdminRepository $repository, string $userId) use ($request): void {
            $schoolId = $request->string('school_id');
            if ($schoolId === '') {
                throw new \InvalidArgumentException('Kies een school.');
            }

            $adminId = $repository->createSchoolAdmin(
                $schoolId,
                $request->string('admin_naam'),
                $request->string('admin_email'),
                (string) $request->input('admin_wachtwoord', ''),
            );

            $this->audit('platform.school_admin_created', $userId, $adminId, $request);
            NotificationBag::success('School-admin is aangemaakt.');
        });
    }

    public function archiveCustomer(Request $request): Response
    {
        return $this->storeAction($request, function (PlatformAdminRepository $repository, string $userId) use ($request): void {
            $schoolId = $request->string('school_id');
            if ($schoolId === '') {
                throw new \InvalidArgumentException('Kies een klant.');
            }

            $repository->archiveCustomer($schoolId);
            $this->audit('platform.customer_archived', $userId, $schoolId, $request);
            NotificationBag::success('Klant is gearchiveerd.');
        });
    }

    public function restoreCustomer(Request $request): Response
    {
        return $this->storeAction($request, function (PlatformAdminRepository $repository, string $userId) use ($request): void {
            $schoolId = $request->string('school_id');
            if ($schoolId === '') {
                throw new \InvalidArgumentException('Kies een klant.');
            }

            $repository->restoreCustomer($schoolId);
            $this->audit('platform.customer_restored', $userId, $schoolId, $request);
            NotificationBag::success('Klant is heractiveerd.');
        });
    }

    private function storeAction(Request $request, callable $callback, string $redirectTo = '/roostar-admin'): Response
    {
        $user = AuthSession::userContext();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::PLATFORM_MANAGE)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect($redirectTo);
        }

        $repository = new PlatformAdminRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        try {
            $callback($repository, $user->id);
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable $error) {
            NotificationBag::error('Roostar Admin actie is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect($redirectTo);
    }

    private function audit(string $action, string $userId, string $entityId, Request $request): void
    {
        (new AuditLogger(Connection::get()))->record(
            $action,
            $userId,
            'platform',
            $entityId,
            [],
            (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'),
        );
    }

    private function forbidden(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'roostar-admin',
            'pageTitle' => 'Geen toegang',
            'moduleTitle' => 'Geen toegang',
            'moduleDescription' => 'Je hebt geen recht om Roostar Admin te beheren.',
        ]), 403);
    }
}
