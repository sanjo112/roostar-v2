<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Controllers;

use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Response;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Repositories\ProfileRepository;
use Roostar\Modules\Auth\Services\AuthSession;

final class ProfileController
{
    public function show(): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        $repository = new ProfileRepository(Connection::get(), new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $profile = $repository->find($user->id);

        if (!$profile) {
            AuthSession::logout();
            return Response::redirect('/login');
        }

        return Response::html(AppView::render('auth/profile', [
            'activePage' => 'profiel',
            'pageTitle' => 'Mijn profiel',
            'profile' => $profile,
        ]));
    }
}
