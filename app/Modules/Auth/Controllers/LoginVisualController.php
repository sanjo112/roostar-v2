<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Controllers;

use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Notifications\NotificationBag;
use Roostar\Core\Security\Csrf;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\SchoolLoginVisualService;

final class LoginVisualController
{
    public function show(): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        $service = $this->service();
        $schoolId = $this->schoolIdFor($user);

        return Response::html(AppView::render('settings/index', [
            'activePage' => 'settings',
            'pageTitle' => 'Instellingen',
            'canManageLoginVisual' => $this->canManageLoginVisual($user),
            'csrfToken' => Csrf::token(),
            'loginVisualPath' => $schoolId ? $service->currentPathForSchool($schoolId) : null,
            'schoolLogoPath' => $schoolId ? $service->currentLogoPathForSchool($schoolId) : null,
        ]));
    }

    public function store(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->canManageLoginVisual($user)) {
            return Response::html('Forbidden', 403);
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/settings');
        }

        $schoolId = $this->schoolIdFor($user);
        if ($schoolId === null) {
            NotificationBag::error('Deze instelling is alleen beschikbaar voor gebruikers met een school.');
            return Response::redirect('/settings');
        }

        try {
            $this->service()->storeUpload($schoolId, $_FILES['login_visual'] ?? []);
            NotificationBag::success('Login-afbeelding is opgeslagen.');
        } catch (\Throwable $error) {
            NotificationBag::error('Login-afbeelding opslaan is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect('/settings');
    }

    public function storeLogo(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->canManageLoginVisual($user)) {
            return Response::html('Forbidden', 403);
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/settings');
        }

        $schoolId = $this->schoolIdFor($user);
        if ($schoolId === null) {
            NotificationBag::error('Deze instelling is alleen beschikbaar voor gebruikers met een school.');
            return Response::redirect('/settings');
        }

        try {
            $this->service()->storeLogoUpload($schoolId, $_FILES['school_logo'] ?? []);
            NotificationBag::success('Schoollogo is opgeslagen.');
        } catch (\Throwable $error) {
            NotificationBag::error('Schoollogo opslaan is niet gelukt: ' . $error->getMessage());
        }

        return Response::redirect('/settings');
    }

    public function resetLogo(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->canManageLoginVisual($user)) {
            return Response::html('Forbidden', 403);
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/settings');
        }

        $schoolId = $this->schoolIdFor($user);
        if ($schoolId !== null) {
            $this->service()->resetLogoForSchool($schoolId);
            NotificationBag::success('Schoollogo is verwijderd.');
        }

        return Response::redirect('/settings');
    }

    public function reset(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->canManageLoginVisual($user)) {
            return Response::html('Forbidden', 403);
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/settings');
        }

        $schoolId = $this->schoolIdFor($user);
        if ($schoolId !== null) {
            $this->service()->resetForSchool($schoolId);
            NotificationBag::success('Login-afbeelding is teruggezet naar de standaard afbeelding.');
        }

        return Response::redirect('/settings');
    }

    private function service(): SchoolLoginVisualService
    {
        return new SchoolLoginVisualService(Connection::get(), $_ENV['APP_KEY'] ?? '');
    }

    private function canManageLoginVisual(object $user): bool
    {
        return method_exists($user, 'hasPermission') && $user->hasPermission(PermissionRegistry::SCHOOL_MANAGE);
    }

    private function schoolIdFor(object $user): ?string
    {
        return is_string($user->schoolId ?? null) && $user->schoolId !== '' ? $user->schoolId : null;
    }
}
