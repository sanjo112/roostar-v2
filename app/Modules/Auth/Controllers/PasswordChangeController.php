<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Controllers;

use Roostar\Core\Audit\AuditLogger;
use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Security\Csrf;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\PasswordService;

final class PasswordChangeController
{
    public function show(): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        return Response::html(AppView::render('auth/change-password', [
            'activePage' => 'profiel',
            'pageTitle' => 'Nieuw wachtwoord',
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['password_change_error'] ?? '',
        ]));
    }

    public function store(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!Csrf::verify($request->string('_token'))) {
            $_SESSION['password_change_error'] = 'Je sessie is verlopen. Probeer opnieuw.';
            return Response::redirect('/wachtwoord-wijzigen');
        }

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $confirmPassword = (string) $request->input('new_password_confirmation', '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $_SESSION['password_change_error'] = 'Vul alle velden in.';
            return Response::redirect('/wachtwoord-wijzigen');
        }

        if (strlen($newPassword) < 10) {
            $_SESSION['password_change_error'] = 'Gebruik minimaal 10 tekens voor je nieuwe wachtwoord.';
            return Response::redirect('/wachtwoord-wijzigen');
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['password_change_error'] = 'De nieuwe wachtwoorden zijn niet gelijk.';
            return Response::redirect('/wachtwoord-wijzigen');
        }

        $db = Connection::get();
        $passwords = new PasswordService($db);

        if (!$passwords->verifyCurrentPassword($user->id, $currentPassword)) {
            $_SESSION['password_change_error'] = 'Het tijdelijke of huidige wachtwoord klopt niet.';
            return Response::redirect('/wachtwoord-wijzigen');
        }

        $passwords->changePassword($user->id, $newPassword);
        (new AuditLogger($db))->record(
            'auth.password.changed',
            $user->id,
            'user',
            $user->id,
            [],
            (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'),
        );

        unset($_SESSION['password_change_error']);

        return Response::redirect('/');
    }
}
