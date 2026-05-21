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

    private function storeAction(Request $request, callable $callback): Response
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
            return Response::redirect('/roostar-admin');
        }

        $repository = new PlatformAdminRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        try {
            $callback($repository, $user->id);
        } catch (\InvalidArgumentException $error) {
            NotificationBag::warning($error->getMessage());
        } catch (\Throwable $error) {
            NotificationBag::error('Roostar Admin actie is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect('/roostar-admin');
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
