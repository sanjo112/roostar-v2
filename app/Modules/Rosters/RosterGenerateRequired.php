<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters;

use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Http\Middleware\Middleware;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Services\AuthSession;

final class RosterGenerateRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response|string
    {
        $user = AuthSession::userContext();
        $schoolId = $request->string('school_id', $user?->schoolId ?? '');

        if (!$user || $schoolId === '' || !$user->hasPermission(PermissionRegistry::ROSTER_GENERATE, 'school', $schoolId)) {
            return Response::html(
                AppView::render('module-placeholder', [
                    'activePage' => 'rooster-genereren',
                    'pageTitle' => 'Geen toegang',
                    'moduleTitle' => 'Geen toegang',
                    'moduleDescription' => 'Je hebt geen recht om voor deze school roosters te genereren.',
                ]),
                403,
            );
        }

        return $next($request);
    }
}
