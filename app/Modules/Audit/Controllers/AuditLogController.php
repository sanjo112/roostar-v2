<?php

declare(strict_types=1);

namespace Roostar\Modules\Audit\Controllers;

use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Response;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\View\AppView;
use Roostar\Modules\Audit\Repositories\AuditLogRepository;
use Roostar\Modules\Auth\Services\AuthSession;

final class AuditLogController
{
    public function index(): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::AUDIT_VIEW)) {
            return Response::html(AppView::render('module-placeholder', [
                'activePage' => 'auditlog',
                'pageTitle' => 'Geen toegang',
                'moduleTitle' => 'Geen toegang',
                'moduleDescription' => 'Je hebt geen recht om de auditlog te bekijken.',
            ]), 403);
        }

        $repository = new AuditLogRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        return Response::html(AppView::render('audit/index', [
            'activePage' => 'auditlog',
            'pageTitle' => 'Auditlog',
            'events' => $repository->recentFor($user),
        ]));
    }
}
