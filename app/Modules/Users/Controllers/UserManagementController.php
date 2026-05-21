<?php

declare(strict_types=1);

namespace Roostar\Modules\Users\Controllers;

use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Audit\AuditLogger;
use Roostar\Core\Database\Connection;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;
use Roostar\Core\Notifications\NotificationBag;
use Roostar\Core\Security\Csrf;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\View\AppView;
use Roostar\Modules\Auth\Services\AuthSession;
use Roostar\Modules\Auth\Services\PasswordService;
use Roostar\Modules\Schools\Repositories\SchoolRepository;
use Roostar\Modules\Users\UserCreator;
use Roostar\Modules\Users\Repositories\UserDirectoryRepository;

final class UserManagementController
{
    public function index(Request $request): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::USERS_MANAGE)) {
            return $this->forbidden();
        }

        $db = Connection::get();
        $encryptor = new Encryptor($_ENV['ENCRYPTION_KEY'] ?? '');
        $users = new UserDirectoryRepository($db, $encryptor);
        $schools = new SchoolRepository($db, $encryptor);
        $schoolFilter = $request->string('school_id') ?: null;
        $roleOptions = $this->roleOptionsFor($user->role);
        $roleFilter = $this->validRoleFilter($request->string('role'), $roleOptions);
        $statusFilter = $this->validStatusFilter($request->string('status'));
        $temporaryPassword = $_SESSION['user_temporary_password'] ?? '';
        $temporaryPasswordUser = $_SESSION['user_temporary_password_user'] ?? [];

        unset(
            $_SESSION['user_temporary_password'],
            $_SESSION['user_temporary_password_user'],
        );

        return Response::html(AppView::render('users/index', [
            'activePage' => 'gebruikers',
            'pageTitle' => 'Gebruikers',
            'users' => $users->listFor($user, $schoolFilter, $roleFilter, $statusFilter),
            'roleCounts' => $users->countsByRole($user),
            'schools' => $schools->accessibleFor($user),
            'schoolFilter' => $schoolFilter,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'roleOptions' => $roleOptions,
            'csrfToken' => Csrf::token(),
            'temporaryPassword' => $temporaryPassword,
            'temporaryPasswordUser' => is_array($temporaryPasswordUser) ? $temporaryPasswordUser : [],
            'currentUserId' => $user->id,
        ]));
    }

    public function create(): Response
    {
        return Response::redirect('/gebruikers');
    }

    public function store(Request $request): Response
    {
        $user = AuthSession::userContext();
        $ipAddress = (string) ($request->server['REMOTE_ADDR'] ?? 'unknown');

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::USERS_MANAGE)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/gebruikers');
        }

        $role = $request->string('role');
        $name = $request->string('name');
        $email = $request->string('email');
        $password = (string) $request->input('password', '');
        $schoolId = $request->string('school_id');

        if ($name === '' || $email === '' || $password === '' || $role === '' || $schoolId === '') {
            NotificationBag::warning('Vul alle verplichte velden in.');
            return Response::redirect('/gebruikers');
        }

        if (strlen($password) < 8) {
            NotificationBag::warning('Het wachtwoord moet minimaal 8 tekens zijn.');
            return Response::redirect('/gebruikers');
        }

        if (!$this->canAssignRole($user->role, $role)) {
            NotificationBag::error('Je mag deze rol niet toekennen.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'role_denied', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return Response::redirect('/gebruikers');
        }

        if (!$user->hasPermission(PermissionRegistry::USERS_MANAGE, 'school', $schoolId)) {
            NotificationBag::error('Je mag geen gebruikers aanmaken voor deze school.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'school_scope_denied', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return Response::redirect('/gebruikers');
        }

        $db = Connection::get();
        $creator = new UserCreator($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        if ($creator->emailExists($email)) {
            NotificationBag::warning('Er bestaat al een gebruiker met dit e-mailadres.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'duplicate_email', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return Response::redirect('/gebruikers');
        }

        $userId = $creator->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'school_id' => $schoolId,
            'scholengroep_id' => $role === 'sg_admin' ? $user->scholengroepId : null,
        ]);

        $grants = new PermissionGrantRepository($db);
        foreach (RoleDefaults::basePermissions($role) as $permission) {
            $grants->grant($userId, $permission, 'school', $schoolId);
        }

        $audit = new AuditLogger($db);
        $audit->record('users.created', $user->id, 'user', $userId, [
            'target_role' => $role,
            'school_id' => $schoolId,
        ], $ipAddress);

        unset($_SESSION['user_create_error']);
        NotificationBag::success('Gebruiker is aangemaakt.');

        return Response::redirect('/gebruikers');
    }

    public function deactivate(Request $request): Response
    {
        return $this->manageExistingUser($request, 'users.deactivated', function (array $target, UserDirectoryRepository $users) use ($request): Response {
            $actor = AuthSession::userContext();

            if (!$actor) {
                return Response::redirect('/login');
            }

            if ($target['id'] === $actor->id) {
                NotificationBag::error('Je kunt je eigen account niet deactiveren.');
                return Response::redirect('/gebruikers');
            }

            if (!$target['active']) {
                NotificationBag::warning('Deze gebruiker is al inactief.');
                return Response::redirect('/gebruikers');
            }

            $users->deactivate((string) $target['id']);
            $this->auditAction('users.deactivated', $actor->id, (string) $target['id'], [
                'target_role' => $target['role'],
                'school_id' => $target['school_id'],
            ], $request);

            NotificationBag::success('Gebruiker is gedeactiveerd.');

            return Response::redirect('/gebruikers');
        });
    }

    public function resetPassword(Request $request): Response
    {
        return $this->manageExistingUser($request, 'users.password_reset', function (array $target, UserDirectoryRepository $users) use ($request): Response {
            unset($users);
            $actor = AuthSession::userContext();

            if (!$actor) {
                return Response::redirect('/login');
            }

            if (!$target['active']) {
                NotificationBag::error('Je kunt geen wachtwoord resetten voor een inactieve gebruiker.');
                return Response::redirect('/gebruikers');
            }

            $db = Connection::get();
            $temporaryPassword = PasswordService::temporaryPassword();
            (new PasswordService($db))->setTemporaryPassword((string) $target['id'], $temporaryPassword);
            $this->auditAction('users.password_reset', $actor->id, (string) $target['id'], [
                'target_role' => $target['role'],
                'school_id' => $target['school_id'],
            ], $request);

            $_SESSION['user_temporary_password'] = $temporaryPassword;
            $_SESSION['user_temporary_password_user'] = [
                'name' => (string) $target['naam'],
                'email' => (string) $target['email'],
            ];

            return Response::redirect('/gebruikers');
        });
    }

    public function reactivate(Request $request): Response
    {
        return $this->manageExistingUser($request, 'users.reactivated', function (array $target, UserDirectoryRepository $users) use ($request): Response {
            $actor = AuthSession::userContext();

            if (!$actor) {
                return Response::redirect('/login');
            }

            if ($target['active']) {
                NotificationBag::warning('Deze gebruiker is al actief.');
                return Response::redirect('/gebruikers');
            }

            $users->reactivate((string) $target['id']);
            $this->auditAction('users.reactivated', $actor->id, (string) $target['id'], [
                'target_role' => $target['role'],
                'school_id' => $target['school_id'],
            ], $request);

            NotificationBag::success('Gebruiker is heractiveerd.');

            return Response::redirect('/gebruikers');
        });
    }

    private function forbidden(): Response
    {
        return Response::html(AppView::render('module-placeholder', [
            'activePage' => 'gebruikers',
            'pageTitle' => 'Geen toegang',
            'moduleTitle' => 'Geen toegang',
            'moduleDescription' => 'Je hebt geen recht om gebruikers te beheren.',
        ]), 403);
    }

    private function manageExistingUser(Request $request, string $action, callable $callback): Response
    {
        $user = AuthSession::userContext();

        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$user->hasPermission(PermissionRegistry::USERS_MANAGE)) {
            return $this->forbidden();
        }

        if (!Csrf::verify($request->string('_token'))) {
            NotificationBag::error('Je sessie is verlopen. Probeer opnieuw.');
            return Response::redirect('/gebruikers');
        }

        $targetUserId = $request->string('user_id');

        if ($targetUserId === '') {
            NotificationBag::warning('Kies eerst een gebruiker.');
            return Response::redirect('/gebruikers');
        }

        $db = Connection::get();
        $users = new UserDirectoryRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $target = $users->findManageableById($user, $targetUserId);

        if (!$target) {
            $this->auditAction($action . '.denied', $user->id, null, [
                'reason' => 'target_not_in_scope',
                'target_user_id' => $targetUserId,
            ], $request);
            NotificationBag::error('Je mag deze gebruiker niet beheren.');
            return Response::redirect('/gebruikers');
        }

        if (!$target['school_id'] || !$user->hasPermission(PermissionRegistry::USERS_MANAGE, 'school', (string) $target['school_id'])) {
            $this->auditAction($action . '.denied', $user->id, (string) $target['id'], [
                'reason' => 'school_scope_denied',
                'school_id' => $target['school_id'],
            ], $request);
            NotificationBag::error('Je mag deze gebruiker niet beheren.');
            return Response::redirect('/gebruikers');
        }

        return $callback($target, $users);
    }

    private function canAssignRole(string $currentRole, string $targetRole): bool
    {
        $allowed = match ($currentRole) {
            'roostar_admin' => ['roostar_admin', 'sg_admin', 'school_admin', 'afdelingsleider', 'rooster_medewerker', 'leraar', 'leerling'],
            'sg_admin' => ['school_admin', 'afdelingsleider', 'rooster_medewerker', 'leraar', 'leerling'],
            'school_admin' => ['afdelingsleider', 'rooster_medewerker', 'leraar', 'leerling'],
            default => [],
        };

        return in_array($targetRole, $allowed, true);
    }

    private function validRoleFilter(string $role, array $roleOptions): ?string
    {
        return array_key_exists($role, $roleOptions) ? $role : null;
    }

    private function validStatusFilter(string $status): ?string
    {
        return in_array($status, ['active', 'inactive'], true) ? $status : null;
    }

    private function roleOptionsFor(string $currentRole): array
    {
        $roles = [
            'sg_admin' => 'Scholengroep admin',
            'school_admin' => 'School admin',
            'afdelingsleider' => 'Afdelingsleider',
            'rooster_medewerker' => 'Roostermedewerker',
            'leraar' => 'Leraar',
            'leerling' => 'Leerling',
        ];

        if ($currentRole !== 'roostar_admin') {
            unset($roles['sg_admin']);
        }

        return $roles;
    }

    private function auditDeniedCreate(string $userId, string $ipAddress, string $reason, array $metadata): void
    {
        try {
            $audit = new AuditLogger(Connection::get());
            $audit->record('users.create.denied', $userId, 'user', null, [
                'reason' => $reason,
                ...$metadata,
            ], $ipAddress);
        } catch (\Throwable) {
            // A denied action should still return cleanly when audit storage is temporarily unavailable.
        }
    }

    private function auditAction(string $action, string $actorId, ?string $entityId, array $metadata, Request $request): void
    {
        $audit = new AuditLogger(Connection::get());
        $audit->record(
            $action,
            $actorId,
            'user',
            $entityId,
            $metadata,
            (string) ($request->server['REMOTE_ADDR'] ?? 'unknown'),
        );
    }
}
