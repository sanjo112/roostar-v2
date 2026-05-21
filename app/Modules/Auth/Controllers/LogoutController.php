<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Controllers;

use Roostar\Core\Http\Response;
use Roostar\Modules\Auth\Services\AuthSession;

final class LogoutController
{
    public function __invoke(): Response
    {
        AuthSession::logout();

        return Response::redirect('/login');
    }
}

