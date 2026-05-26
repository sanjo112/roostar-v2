<?php

declare(strict_types=1);

use Roostar\Core\Http\Response;
use Roostar\Core\Http\Middleware\AuthRequired;
use Roostar\Core\Http\Router;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\View\AppView;
use Roostar\Modules\Absence\Controllers\AbsenceController;
use Roostar\Core\Notifications\NotificationController;
use Roostar\Modules\Audit\Controllers\AuditLogController;
use Roostar\Modules\Auth\Controllers\LoginController;
use Roostar\Modules\Auth\Controllers\LogoutController;
use Roostar\Modules\Auth\Controllers\PasswordChangeController;
use Roostar\Modules\Auth\Controllers\ProfileController;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Dashboard\ModulePlaceholderController;
use Roostar\Modules\Platform\Controllers\PlatformAdminController;
use Roostar\Modules\RosterData\Controllers\RosterDataController;
use Roostar\Modules\Rosters\RosterController;
use Roostar\Modules\Rosters\RosterPolicy;
use Roostar\Modules\Rosters\RosterGenerateRequired;
use Roostar\Modules\Students\Controllers\StudentController;
use Roostar\Modules\TestPlanning\Controllers\TestPlanningController;
use Roostar\Modules\Users\Controllers\UserManagementController;

return static function (Router $router): void {
    $notifications = new NotificationController();
    $absence = new AbsenceController();
    $audit = new AuditLogController();
    $login = new LoginController();
    $logout = new LogoutController();
    $passwordChange = new PasswordChangeController();
    $profile = new ProfileController();
    $placeholder = new ModulePlaceholderController();
    $platformAdmin = new PlatformAdminController();
    $rosterData = new RosterDataController();
    $rosters = new RosterController();
    $students = new StudentController();
    $testPlanning = new TestPlanningController();
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

    $router->get('/', static function (): Response {
        $user = AuthSession::userContext();

        if ($user?->hasPermission(PermissionRegistry::PLATFORM_MANAGE)) {
            return Response::redirect('/roostar-admin');
        }

        if ($user?->hasPermission(PermissionRegistry::ROSTER_VIEW)) {
            return Response::redirect('/rooster');
        }

        if ($user?->hasPermission(PermissionRegistry::SCHOOL_MANAGE)) {
            return Response::redirect('/stamdata');
        }

        return Response::redirect('/profiel');
    }, $authRequired);
    $router->get('/rooster', [$rosters, 'index'], $authRequired);
    $router->get('/rooster/export/pdf', [$rosters, 'exportPdf'], $authRequired);
    $router->get('/roosters/genereren', [$rosters, 'generate'], $rosterGenerateRequired);
    $router->post('/roosters/genereren', [$rosters, 'generate'], $rosterGenerateRequired);
    $router->post('/roosters/lessen/verplaats', [$rosters, 'moveLesson'], $rosterGenerateRequired);
    $router->post('/rooster/week/lessen/verplaats', [$rosters, 'moveWeekLesson'], $authRequired);
    $router->get('/stamdata', [$rosterData, 'masterData'], $authRequired);
    $router->get('/schooljaar', [$rosterData, 'schoolYears'], $authRequired);
    $router->post('/schooljaar', [$rosterData, 'storeSchoolYear'], $authRequired);
    $router->post('/schooljaar/bewerk', [$rosterData, 'updateSchoolYear'], $authRequired);
    $router->post('/schooljaar/periodes', [$rosterData, 'storePeriod'], $authRequired);
    $router->post('/schooljaar/periodes/bewerk', [$rosterData, 'updatePeriod'], $authRequired);
    $router->post('/schooljaar/periodes/verwijder', [$rosterData, 'deletePeriod'], $authRequired);
    $router->post('/schooljaar/vrije-dagen', [$rosterData, 'storeSchoolYearBreak'], $authRequired);
    $router->post('/schooljaar/vrije-dagen/bewerk', [$rosterData, 'updateSchoolYearBreak'], $authRequired);
    $router->post('/schooljaar/vrije-dagen/verwijder', [$rosterData, 'deleteSchoolYearBreak'], $authRequired);
    $router->get('/klassen', [$rosterData, 'classes'], $authRequired);
    $router->post('/klassen', [$rosterData, 'storeClass'], $authRequired);
    $router->post('/klassen/bewerk', [$rosterData, 'updateClass'], $authRequired);
    $router->get('/klassen/export', [$rosterData, 'exportClasses'], $authRequired);
    $router->post('/klassen/import', [$rosterData, 'importClasses'], $authRequired);
    $router->get('/afdeling', [$rosterData, 'education'], $authRequired);
    $router->post('/opleidingen', [$rosterData, 'storeProgram'], $authRequired);
    $router->post('/opleidingen/bewerk', [$rosterData, 'updateProgram'], $authRequired);
    $router->post('/vakken', [$rosterData, 'storeSubject'], $authRequired);
    $router->post('/vakken/bewerk', [$rosterData, 'updateSubject'], $authRequired);
    $router->get('/vakken/export', [$rosterData, 'exportSubjects'], $authRequired);
    $router->post('/vakken/import', [$rosterData, 'importSubjects'], $authRequired);
    $router->post('/locaties', [$rosterData, 'storeLocation'], $authRequired);
    $router->post('/locaties/bewerk', [$rosterData, 'updateLocation'], $authRequired);
    $router->post('/locaties/verwijder', [$rosterData, 'deleteLocation'], $authRequired);
    $router->post('/lokalen', [$rosterData, 'storeRoom'], $authRequired);
    $router->post('/lokalen/bewerk', [$rosterData, 'updateRoom'], $authRequired);
    $router->post('/lokalen/kopieer', [$rosterData, 'copyRoom'], $authRequired);
    $router->post('/lokalen/verwijder', [$rosterData, 'deleteRoom'], $authRequired);
    $router->get('/leraren', [$rosterData, 'teachers'], $authRequired);
    $router->post('/leraren', [$rosterData, 'storeTeacher'], $authRequired);
    $router->post('/leraren/bewerk', [$rosterData, 'updateTeacher'], $authRequired);
    $router->post('/leraren/verwijder', [$rosterData, 'deleteTeacher'], $authRequired);
    $router->get('/leraren/export', [$rosterData, 'exportTeachers'], $authRequired);
    $router->post('/leraren/import', [$rosterData, 'importTeachers'], $authRequired);
    $router->get('/leerlingen', [$students, 'index'], $authRequired);
    $router->post('/leerlingen', [$students, 'store'], $authRequired);
    $router->post('/leerlingen/bewerk', [$students, 'update'], $authRequired);
    $router->post('/leerlingen/verwijder', [$students, 'delete'], $authRequired);
    $router->get('/leerlingen/export', [$students, 'export'], $authRequired);
    $router->post('/leerlingen/import', [$students, 'import'], $authRequired);
    $router->get('/gebruikers', [$users, 'index'], $authRequired);
    $router->get('/gebruikers/nieuw', [$users, 'create'], $authRequired);
    $router->post('/gebruikers', [$users, 'store'], $authRequired);
    $router->post('/gebruikers/deactiveer', [$users, 'deactivate'], $authRequired);
    $router->post('/gebruikers/heractiveer', [$users, 'reactivate'], $authRequired);
    $router->post('/gebruikers/reset-wachtwoord', [$users, 'resetPassword'], $authRequired);
    $router->get('/auditlog', [$audit, 'index'], $authRequired);
    $router->get('/ziekte', [$absence, 'index'], $authRequired);
    $router->post('/ziekte', [$absence, 'store'], $authRequired);
    $router->post('/ziekte/vervanging', [$absence, 'replace'], $authRequired);
    $router->post('/ziekte/vervanging/langdurig', [$absence, 'replaceRange'], $authRequired);
    $router->post('/ziekte/uitroosteren', [$absence, 'cancelLesson'], $authRequired);
    $router->post('/ziekte/vervanging/verwijder', [$absence, 'clearReplacement'], $authRequired);
    $router->post('/ziekte/hersteld', [$absence, 'resolve'], $authRequired);
    $router->get('/toetsweken', [$testPlanning, 'index'], $authRequired);
    $router->post('/toetsweken', [$testPlanning, 'storeTestWeek'], $authRequired);
    $router->post('/toetsweken/bewerk', [$testPlanning, 'updateTestWeek'], $authRequired);
    $router->post('/toetsweken/verwijder', [$testPlanning, 'deleteTestWeek'], $authRequired);
    $router->post('/toetsen', [$testPlanning, 'saveTest'], $authRequired);
    $router->post('/toetsen/verwijder', [$testPlanning, 'deleteTest'], $authRequired);
    $router->post('/toetsweken/surveillance', [$testPlanning, 'saveSurveillance'], $authRequired);
    $router->post('/toetsweken/surveillance/voorstel', [$testPlanning, 'proposeSurveillance'], $authRequired);
    $router->get('/roostar-admin', [$platformAdmin, 'index'], $authRequired);
    $router->post('/roostar-admin/klanten', [$platformAdmin, 'storeCustomer'], $authRequired);
    $router->post('/roostar-admin/klanten/archiveer', [$platformAdmin, 'archiveCustomer'], $authRequired);
    $router->post('/roostar-admin/klanten/heractiveer', [$platformAdmin, 'restoreCustomer'], $authRequired);
    $router->post('/roostar-admin/school-admins', [$platformAdmin, 'storeSchoolAdmin'], $authRequired);
    $router->get('/roundbuttons', static fn (): Response => Response::html(AppView::render('style-lab/roundbuttons', [
        'activePage' => 'settings',
        'pageTitle' => 'Round buttons',
    ])), $authRequired);

    foreach ([
        '/stage' => 'stage',
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
