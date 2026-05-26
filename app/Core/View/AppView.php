<?php

declare(strict_types=1);

namespace Roostar\Core\View;

use Roostar\Core\Http\View;
use Roostar\Core\Database\Connection;
use Roostar\Core\Notifications\NotificationBag;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\SchoolLoginVisualService;
use Roostar\Modules\Navigation\NavigationBuilder;

final class AppView
{
    public static function render(string $page, array $data = []): string
    {
        $activePage = (string) ($data['activePage'] ?? $page);
        $context = AuthSession::userContext();
        $role = (string) ($data['role'] ?? $context?->role ?? 'sg_admin');
        $pageTitle = (string) ($data['pageTitle'] ?? 'Dashboard');
        $user = $data['user'] ?? [
            'naam' => 'Roostar V2',
            'initials' => $context ? strtoupper(substr($context->role, 0, 2)) : 'V2',
            'role' => $role,
        ];

        $content = View::render('pages/' . $page, $data);
        $notifications = NotificationBag::pull();
        $schoolLogoPath = $data['schoolLogoPath'] ?? null;

        if (!is_string($schoolLogoPath) && $context?->schoolId) {
            try {
                $schoolLogoPath = (new SchoolLoginVisualService(Connection::get(), $_ENV['APP_KEY'] ?? ''))
                    ->currentLogoPathForSchool($context->schoolId);
            } catch (\Throwable) {
                $schoolLogoPath = null;
            }
        }

        return View::render('layouts/app', [
            ...$data,
            'activePage' => $activePage,
            'content' => $content,
            'navGroups' => $context ? NavigationBuilder::forUser($context, $activePage) : NavigationBuilder::forRole($role, $activePage),
            'notifications' => $notifications,
            'notificationCenter' => NotificationBag::recent() ?: $notifications,
            'pageTitle' => $pageTitle,
            'schoolLogoPath' => $schoolLogoPath,
            'user' => $user,
        ]);
    }
}
