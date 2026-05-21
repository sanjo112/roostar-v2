<?php

declare(strict_types=1);

namespace Roostar\Core\Http\Middleware;

use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Modules\Auth\Services\AuthSession;

final class AuthRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response|string
    {
        if (!AuthSession::check()) {
            return Response::redirect('/login');
        }

        $user = AuthSession::userContext();
        $allowedDuringPasswordChange = ['/wachtwoord-wijzigen', '/logout'];

        if ($user?->forcePasswordChange && !in_array($request->path, $allowedDuringPasswordChange, true)) {
            return Response::redirect('/wachtwoord-wijzigen');
        }

        return $next($request);
    }
}
