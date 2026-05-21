<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Controllers;

use Roostar\Core\Database\Connection;
use Roostar\Core\Audit\AuditLogger;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Http\View;
use Roostar\Core\Security\Csrf;
use Roostar\Core\Support\Str;
use Roostar\Modules\Auth\Repositories\UserRepository;
use Roostar\Modules\Auth\Services\AuthService;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\LoginRateLimiter;

final class LoginController
{
    public function show(): Response
    {
        if (AuthSession::check()) {
            return Response::redirect('/');
        }

        return Response::html(View::render('pages/auth/login', [
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['login_error'] ?? '',
        ]));
    }

    public function store(Request $request): Response
    {
        if (!Csrf::verify($request->string('_token'))) {
            $_SESSION['login_error'] = 'Je sessie is verlopen. Probeer opnieuw.';
            return Response::redirect('/login');
        }

        try {
            $db = Connection::get();
            $email = $request->string('email');
            $ipAddress = (string) ($request->server['REMOTE_ADDR'] ?? 'unknown');
            $rateLimiter = new LoginRateLimiter($db);
            $audit = new AuditLogger($db);

            if ($rateLimiter->tooManyAttempts($email, $ipAddress)) {
                $result = ['success' => false, 'error' => 'Te veel mislukte pogingen. Probeer later opnieuw.'];
                $audit->record('auth.login.rate_limited', null, 'user', null, [
                    'email_hash' => Str::searchHash($email),
                ], $ipAddress);
            } else {
                $auth = new AuthService(new UserRepository($db));
                $result = $auth->attempt($email, (string) $request->input('password', ''));

                if ($result['success']) {
                    $rateLimiter->clear($email, $ipAddress);
                    $audit->record('auth.login.succeeded', (string) $result['user_id'], 'user', (string) $result['user_id'], [], $ipAddress);
                } else {
                    $rateLimiter->recordFailedAttempt($email, $ipAddress);
                    $audit->record('auth.login.failed', null, 'user', null, [
                        'email_hash' => Str::searchHash($email),
                    ], $ipAddress);
                }
            }
        } catch (\Throwable) {
            $result = ['success' => false, 'error' => 'Inloggen is nog niet beschikbaar omdat de database niet is geconfigureerd.'];
        }

        if (!$result['success']) {
            $_SESSION['login_error'] = $result['error'];
            return Response::redirect('/login');
        }

        unset($_SESSION['login_error']);

        return Response::redirect('/');
    }
}
