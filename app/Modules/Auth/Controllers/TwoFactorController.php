<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Controllers;

use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Http\View;
use Roostar\Core\Security\Csrf;
use Roostar\Core\Security\QrCode;
use Roostar\Core\Security\Totp;
use Roostar\Modules\Auth\Repositories\TwoFactorRepository;
use Roostar\Modules\Auth\Repositories\UserRepository;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\SchoolLoginVisualService;

final class TwoFactorController
{
    public function showSetup(): Response
    {
        $pending = $_SESSION['pending_2fa_user'] ?? null;

        if (!$pending) {
            return Response::redirect('/login');
        }

        $secret = Totp::generateSecret();
        $_SESSION['pending_2fa_secret'] = $secret;
        $otpauthUri = $this->otpauthUri((string) $pending, $secret);

        return Response::html(View::render('pages/auth/2fa-setup', [
            'secret' => $secret,
            'user_id' => $pending,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['2fa_error'] ?? '',
            'otpauthUri' => $otpauthUri,
            'qrCodeSvg' => QrCode::svg($otpauthUri),
        ]));
    }

    public function storeSetup(Request $request): Response
    {
        $pending = $_SESSION['pending_2fa_user'] ?? null;
        $secret = $_SESSION['pending_2fa_secret'] ?? null;

        if (!$pending || !$secret) {
            return Response::redirect('/login');
        }

        if (!Csrf::verify($request->string('_token'))) {
            $_SESSION['2fa_error'] = 'Je sessie is verlopen. Probeer opnieuw.';
            return Response::redirect('/2fa/setup');
        }

        $code = $request->string('code');

        if (!Totp::verify($secret, $code)) {
            $_SESSION['2fa_error'] = 'Onjuiste code. Probeer opnieuw.';
            return Response::redirect('/2fa/setup');
        }

        $repo = new TwoFactorRepository(Connection::get());
        $repo->upsert($pending, $secret, true, true);

        unset($_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_secret'], $_SESSION['2fa_error']);

        AuthSession::login($pending);
        (new UserRepository(Connection::get()))->touchLastLogin((string) $pending);
        $this->syncLoginVisualCookie((string) $pending);

        return Response::redirect('/');
    }

    public function showChallenge(): Response
    {
        $pending = $_SESSION['pending_2fa_user'] ?? null;

        if (!$pending) {
            return Response::redirect('/login');
        }

        return Response::html(View::render('pages/auth/2fa-challenge', [
            'user_id' => $pending,
            'error' => $_SESSION['2fa_error'] ?? '',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function storeChallenge(Request $request): Response
    {
        $pending = $_SESSION['pending_2fa_user'] ?? null;

        if (!$pending) {
            return Response::redirect('/login');
        }

        if (!Csrf::verify($request->string('_token'))) {
            $_SESSION['2fa_error'] = 'Je sessie is verlopen. Probeer opnieuw.';
            return Response::redirect('/2fa/challenge');
        }

        $repo = new TwoFactorRepository(Connection::get());
        $t = $repo->get($pending);

        if (!$t || empty($t['secret'])) {
            return Response::redirect('/login');
        }

        $code = $request->string('code');

        if (!Totp::verify($t['secret'], $code)) {
            $_SESSION['2fa_error'] = 'Onjuiste code.';
            return Response::redirect('/2fa/challenge');
        }

        unset($_SESSION['pending_2fa_user'], $_SESSION['2fa_error']);
        AuthSession::login($pending);
        (new UserRepository(Connection::get()))->touchLastLogin((string) $pending);
        $this->syncLoginVisualCookie((string) $pending);

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
            // Two-factor login should continue even when this preference cannot be loaded.
        }
    }

    private function otpauthUri(string $userId, string $secret): string
    {
        $email = 'Roostar account';

        try {
            $user = (new UserRepository(Connection::get()))->findActiveById($userId);
            if (is_array($user) && is_string($user['email'] ?? null) && $user['email'] !== '') {
                $email = (string) $user['email'];
            }
        } catch (\Throwable) {
            // Fall back to a generic account label.
        }

        $issuer = 'Roostar';

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&digits=6&period=30';
    }
}
