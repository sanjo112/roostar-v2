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
use Roostar\Modules\Auth\Services\SchoolLoginVisualService;

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

                if (isset($result['two_factor'])) {
                    $rateLimiter->clear($email, $ipAddress);
                    $audit->record('auth.login.two_factor_required', (string) $result['user_id'], 'user', (string) $result['user_id'], [], $ipAddress);
                } elseif ($result['success']) {
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

        if (isset($result['two_factor'])) {
            // preserve pending user id for flow
            $_SESSION['pending_2fa_user'] = $result['user_id'] ?? null;
            if ($result['two_factor'] === 'setup') {
                return Response::redirect('/2fa/setup');
            }

            return Response::redirect('/2fa/challenge');
        }

        if (!$result['success']) {
            $_SESSION['login_error'] = $result['error'];
            return Response::redirect('/login');
        }

        unset($_SESSION['login_error']);
        $this->syncLoginVisualCookie((string) $result['user_id']);

        return Response::redirect('/');
    }

    private function syncLoginVisualCookie(string $userId): void
    {
        try {
            $db = Connection::get();
            $user = (new UserRepository($db))->findActiveById($userId);
            $schoolId = is_array($user) ? ($user['school_id'] ?? null) : null;

            (new SchoolLoginVisualService($db, $_ENV['APP_KEY'] ?? ''))
                ->setCookieForSchool(is_string($schoolId) ? $schoolId : null);
        } catch (\Throwable) {
            // Login should never fail because a cosmetic preference cannot be loaded.
        }
    }
}
