<?php

declare(strict_types=1);

use Roostar\Core\Http\Response;
use Roostar\Core\Http\Middleware\AuthRequired;
use Roostar\Core\Http\Router;
use Roostar\Core\Notifications\NotificationController;
use Roostar\Modules\Audit\Controllers\AuditLogController;
use Roostar\Modules\Auth\Controllers\LoginController;
use Roostar\Modules\Auth\Controllers\LogoutController;
use Roostar\Modules\Auth\Controllers\PasswordChangeController;
use Roostar\Modules\Auth\Controllers\ProfileController;
use Roostar\Modules\Dashboard\DashboardController;
use Roostar\Modules\Dashboard\ModulePlaceholderController;
use Roostar\Modules\RosterData\Controllers\RosterDataController;
use Roostar\Modules\Rosters\RosterController;
use Roostar\Modules\Rosters\RosterPolicy;
use Roostar\Modules\Rosters\RosterGenerateRequired;
use Roostar\Modules\Users\Controllers\UserManagementController;

return static function (Router $router): void {
    $dashboard = new DashboardController();
    $notifications = new NotificationController();
    $audit = new AuditLogController();
    $login = new LoginController();
    $logout = new LogoutController();
    $passwordChange = new PasswordChangeController();
    $profile = new ProfileController();
    $placeholder = new ModulePlaceholderController();
    $rosterData = new RosterDataController();
    $rosters = new RosterController();
    $users = new UserManagementController();
    $authRequired = [new AuthRequired()];
    $rosterGenerateRequired = [new AuthRequired(), new RosterGenerateRequired()];

    $router->get('/login', [$login, 'show']);
    $router->post('/login', [$login, 'store']);
    $router->get('/logout', $logout);
    $router->get('/wachtwoord-wijzigen', [$passwordChange, 'show'], $authRequired);
    $router->post('/wachtwoord-wijzigen', [$passwordChange, 'store'], $authRequired);
    $router->get('/profiel', [$profile, 'show'], $authRequired);
    $router->post('/notifications/read', [$notifications, 'markRead'], $authRequired);

    $router->get('/', $dashboard, $authRequired);
    $router->get('/rooster', [$rosters, 'index'], $authRequired);
    $router->get('/roosters/genereren', [$rosters, 'generate'], $rosterGenerateRequired);
    $router->get('/stamdata', [$rosterData, 'masterData'], $authRequired);
    $router->get('/schooljaar', [$rosterData, 'schoolYears'], $authRequired);
    $router->post('/schooljaar', [$rosterData, 'storeSchoolYear'], $authRequired);
    $router->post('/schooljaar/bewerk', [$rosterData, 'updateSchoolYear'], $authRequired);
    $router->get('/klassen', [$rosterData, 'classes'], $authRequired);
    $router->post('/klassen', [$rosterData, 'storeClass'], $authRequired);
    $router->post('/klassen/bewerk', [$rosterData, 'updateClass'], $authRequired);
    $router->get('/afdeling', [$rosterData, 'education'], $authRequired);
    $router->post('/opleidingen', [$rosterData, 'storeProgram'], $authRequired);
    $router->post('/opleidingen/bewerk', [$rosterData, 'updateProgram'], $authRequired);
    $router->post('/vakken', [$rosterData, 'storeSubject'], $authRequired);
    $router->post('/vakken/bewerk', [$rosterData, 'updateSubject'], $authRequired);
    $router->post('/lokalen', [$rosterData, 'storeRoom'], $authRequired);
    $router->post('/lokalen/bewerk', [$rosterData, 'updateRoom'], $authRequired);
    $router->get('/leraren', [$rosterData, 'teachers'], $authRequired);
    $router->get('/gebruikers', [$users, 'index'], $authRequired);
    $router->get('/gebruikers/nieuw', [$users, 'create'], $authRequired);
    $router->post('/gebruikers', [$users, 'store'], $authRequired);
    $router->post('/gebruikers/deactiveer', [$users, 'deactivate'], $authRequired);
    $router->post('/gebruikers/heractiveer', [$users, 'reactivate'], $authRequired);
    $router->post('/gebruikers/reset-wachtwoord', [$users, 'resetPassword'], $authRequired);
    $router->get('/auditlog', [$audit, 'index'], $authRequired);

    foreach ([
        '/roostar-admin' => 'roostar-admin',
        '/ziekte' => 'ziekte',
        '/toetsweken' => 'toetsweken',
        '/stage' => 'stage',
        '/leerlingen' => 'leerlingen',
        '/settings' => 'settings',
    ] as $path => $key) {
        $router->get($path, static fn () => $placeholder->show($key), $authRequired);
    }

    $router->get('/health', static fn () => Response::json([
        'status' => 'ok',
        'app' => 'Roostar V2',
    ]));

    $router->get('/permissions/example', static function () {
        return Response::json([
            'rule' => 'Scholengroep admins may not generate rosters unless explicitly granted roster.generate for the school scope.',
            'policy' => RosterPolicy::class,
        ]);
    }, $authRequired);
};
