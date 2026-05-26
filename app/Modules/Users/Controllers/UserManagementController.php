<?php

declare(strict_types=1);

namespace Roostar\Modules\Users\Controllers;

use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Audit\AuditLogger;
use Roostar\Core\Auth\UserContext;
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
    private const TABS = [
        'gebruikers' => 'Gebruikers',
        'leraren' => 'Leraren',
        'leerlingen' => 'Leerlingen',
    ];

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
        $activeTab = array_key_exists($request->string('tab'), self::TABS) ? $request->string('tab') : 'gebruikers';
        $generalRoleOptions = array_diff_key($roleOptions, ['leraar' => true, 'leerling' => true]);
        $roleFilter = $this->validRoleFilter($request->string('role'), $generalRoleOptions);
        $statusFilter = $this->validStatusFilter($request->string('status'));
        $effectiveRoleFilter = $roleFilter;
        $excludedRoles = [];

        if ($activeTab === 'leraren') {
            $effectiveRoleFilter = 'leraar';
        } elseif ($activeTab === 'leerlingen') {
            $effectiveRoleFilter = 'leerling';
        } elseif ($effectiveRoleFilter === null) {
            $excludedRoles = ['leraar', 'leerling'];
        }

        $temporaryPassword = $_SESSION['user_temporary_password'] ?? '';
        $temporaryPasswordUser = $_SESSION['user_temporary_password_user'] ?? [];

        unset(
            $_SESSION['user_temporary_password'],
            $_SESSION['user_temporary_password_user'],
        );

        return Response::html(AppView::render('users/index', [
            'activePage' => 'gebruikers',
            'pageTitle' => 'Gebruikers',
            'users' => $users->listFor($user, $schoolFilter, $effectiveRoleFilter, $statusFilter, $excludedRoles),
            'roleCounts' => $users->countsByRole($user),
            'tabs' => self::TABS,
            'activeTab' => $activeTab,
            'schools' => $schools->accessibleFor($user),
            'schoolFilter' => $schoolFilter,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'roleOptions' => $roleOptions,
            'filterRoleOptions' => $generalRoleOptions,
            'moduleOptions' => $this->moduleOptionsFor($user),
            'permissionLabels' => PermissionRegistry::labels(),
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
            return $this->redirectToUsers($request);
        }

        $role = $request->string('role');
        $name = $request->string('name');
        $email = $request->string('email');
        $password = (string) $request->input('password', '');
        $schoolId = $request->string('school_id');
        $permissions = $this->selectedPermissions($request);

        if ($name === '' || $email === '' || $password === '' || $role === '' || $schoolId === '') {
            NotificationBag::warning('Vul alle verplichte velden in.');
            return $this->redirectToUsers($request);
        }

        if ($permissions === []) {
            NotificationBag::warning('Kies minimaal één module voor deze gebruiker.');
            return $this->redirectToUsers($request);
        }

        if (strlen($password) < 8) {
            NotificationBag::warning('Het wachtwoord moet minimaal 8 tekens zijn.');
            return $this->redirectToUsers($request);
        }

        if (!$this->canAssignRole($user->role, $role)) {
            NotificationBag::error('Je mag deze rol niet toekennen.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'role_denied', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return $this->redirectToUsers($request);
        }

        if (!$user->hasPermission(PermissionRegistry::USERS_MANAGE, 'school', $schoolId)) {
            NotificationBag::error('Je mag geen gebruikers aanmaken voor deze school.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'school_scope_denied', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return $this->redirectToUsers($request);
        }

        foreach ($permissions as $permission) {
            if (!$this->canAssignPermission($user, $permission, $schoolId)) {
                NotificationBag::error('Je mag een of meer gekozen modules niet toekennen.');
                $this->auditDeniedCreate($user->id, $ipAddress, 'permission_denied', [
                    'target_role' => $role,
                    'school_id' => $schoolId,
                    'permission' => $permission,
                ]);
                return $this->redirectToUsers($request);
            }
        }

        $db = Connection::get();
        $creator = new UserCreator($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));

        if ($creator->emailExists($email)) {
            NotificationBag::warning('Er bestaat al een gebruiker met dit e-mailadres.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'duplicate_email', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return $this->redirectToUsers($request);
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
        $grants->replaceForUser($userId, $this->grantsForPermissions($permissions, $schoolId));

        $audit = new AuditLogger($db);
        $audit->record('users.created', $user->id, 'user', $userId, [
            'target_role' => $role,
            'school_id' => $schoolId,
        ], $ipAddress);

        unset($_SESSION['user_create_error']);
        NotificationBag::success('Gebruiker is aangemaakt.');

        return $this->redirectToUsers($request);
    }

    public function update(Request $request): Response
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
            return $this->redirectToUsers($request);
        }

        $targetUserId = $request->string('user_id');
        $role = $request->string('role');
        $name = $request->string('name');
        $email = $request->string('email');
        $schoolId = $request->string('school_id');
        $permissions = $this->selectedPermissions($request);

        if ($targetUserId === '' || $name === '' || $email === '' || $role === '' || $schoolId === '') {
            NotificationBag::warning('Vul alle verplichte velden in.');
            return $this->redirectToUsers($request);
        }

        if ($permissions === []) {
            NotificationBag::warning('Kies minimaal één module voor deze gebruiker.');
            return $this->redirectToUsers($request);
        }

        $db = Connection::get();
        $users = new UserDirectoryRepository($db, new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''));
        $target = $users->findManageableById($user, $targetUserId);

        if (!$target) {
            NotificationBag::error('Je mag deze gebruiker niet beheren.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'target_not_in_scope', [
                'target_user_id' => $targetUserId,
            ]);
            return $this->redirectToUsers($request);
        }

        if ((string) $target['id'] === $user->id) {
            NotificationBag::error('Je kunt je eigen rechten niet aanpassen.');
            return $this->redirectToUsers($request);
        }

        if ($role !== (string) $target['role'] && !$this->canAssignRole($user->role, $role)) {
            NotificationBag::error('Je mag deze rol niet toekennen.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'role_denied', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return $this->redirectToUsers($request);
        }

        if (!$user->hasPermission(PermissionRegistry::USERS_MANAGE, 'school', $schoolId)) {
            NotificationBag::error('Je mag geen gebruikers beheren voor deze school.');
            $this->auditDeniedCreate($user->id, $ipAddress, 'school_scope_denied', [
                'target_role' => $role,
                'school_id' => $schoolId,
            ]);
            return $this->redirectToUsers($request);
        }

        foreach ($permissions as $permission) {
            if (!$this->canAssignPermission($user, $permission, $schoolId)) {
                NotificationBag::error('Je mag een of meer gekozen modules niet toekennen.');
                $this->auditDeniedCreate($user->id, $ipAddress, 'permission_denied', [
                    'target_role' => $role,
                    'school_id' => $schoolId,
                    'permission' => $permission,
                ]);
                return $this->redirectToUsers($request);
            }
        }

        if ($users->emailExistsForOtherUser($email, $targetUserId)) {
            NotificationBag::warning('Er bestaat al een andere gebruiker met dit e-mailadres.');
            return $this->redirectToUsers($request);
        }

        $users->updateProfile($targetUserId, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'school_id' => $schoolId,
            'scholengroep_id' => $role === 'sg_admin' ? $user->scholengroepId : null,
        ]);

        (new PermissionGrantRepository($db))->replaceForUser($targetUserId, $this->grantsForPermissions($permissions, $schoolId));
        (new AuditLogger($db))->record('users.updated', $user->id, 'user', $targetUserId, [
            'target_role' => $role,
            'school_id' => $schoolId,
            'permissions' => $permissions,
        ], $ipAddress);

        NotificationBag::success('Gebruiker is bijgewerkt.');

        return $this->redirectToUsers($request);
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
                return $this->redirectToUsers($request);
            }

            if (!$target['active']) {
                NotificationBag::warning('Deze gebruiker is al inactief.');
                return $this->redirectToUsers($request);
            }

            $users->deactivate((string) $target['id']);
            $this->auditAction('users.deactivated', $actor->id, (string) $target['id'], [
                'target_role' => $target['role'],
                'school_id' => $target['school_id'],
            ], $request);

            NotificationBag::success('Gebruiker is gedeactiveerd.');

            return $this->redirectToUsers($request);
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
                return $this->redirectToUsers($request);
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

            return $this->redirectToUsers($request);
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
                return $this->redirectToUsers($request);
            }

            $users->reactivate((string) $target['id']);
            $this->auditAction('users.reactivated', $actor->id, (string) $target['id'], [
                'target_role' => $target['role'],
                'school_id' => $target['school_id'],
            ], $request);

            NotificationBag::success('Gebruiker is heractiveerd.');

            return $this->redirectToUsers($request);
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

    private function redirectToUsers(Request $request): Response
    {
        $tab = $request->string('tab');
        $query = array_key_exists($tab, self::TABS) ? '?tab=' . rawurlencode($tab) : '';

        return Response::redirect('/gebruikers' . $query);
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
            return $this->redirectToUsers($request);
        }

        $targetUserId = $request->string('user_id');

        if ($targetUserId === '') {
            NotificationBag::warning('Kies eerst een gebruiker.');
            return $this->redirectToUsers($request);
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
            return $this->redirectToUsers($request);
        }

        if (!$target['school_id'] || !$user->hasPermission(PermissionRegistry::USERS_MANAGE, 'school', (string) $target['school_id'])) {
            $this->auditAction($action . '.denied', $user->id, (string) $target['id'], [
                'reason' => 'school_scope_denied',
                'school_id' => $target['school_id'],
            ], $request);
            NotificationBag::error('Je mag deze gebruiker niet beheren.');
            return $this->redirectToUsers($request);
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

    private function selectedPermissions(Request $request): array
    {
        $modules = $request->input('modules', []);
        $modules = is_array($modules) ? $modules : [];
        $permissions = [];

        foreach ($modules as $module) {
            if (!is_string($module)) {
                continue;
            }

            $permissions = [
                ...$permissions,
                ...match ($module) {
                    'platform' => [PermissionRegistry::PLATFORM_MANAGE],
                    'stamdata' => [PermissionRegistry::SCHOOL_MANAGE],
                    'gebruikers' => [PermissionRegistry::USERS_MANAGE],
                    'auditlog' => [PermissionRegistry::AUDIT_VIEW],
                    'rooster' => [PermissionRegistry::ROSTER_VIEW],
                    'rooster_genereren' => [PermissionRegistry::ROSTER_VIEW, PermissionRegistry::ROSTER_GENERATE],
                    'rooster_bewerken' => [PermissionRegistry::ROSTER_VIEW, PermissionRegistry::ROSTER_EDIT],
                    'ziekte' => [PermissionRegistry::ABSENCE_MANAGE],
                    'toetsplanning' => [PermissionRegistry::TEST_PLANNING_MANAGE],
                    'stage' => [PermissionRegistry::INTERNSHIP_MANAGE],
                    default => [],
                },
            ];
        }

        return array_values(array_unique($permissions));
    }

    private function grantsForPermissions(array $permissions, string $schoolId): array
    {
        return array_map(static function (string $permission) use ($schoolId): array {
            return [
                'permission' => $permission,
                'scope_type' => $permission === PermissionRegistry::PLATFORM_MANAGE ? 'platform' : 'school',
                'scope_id' => $permission === PermissionRegistry::PLATFORM_MANAGE ? 'platform' : $schoolId,
            ];
        }, $permissions);
    }

    private function moduleOptionsFor(UserContext $user): array
    {
        $options = [
            'rooster' => ['label' => 'Rooster bekijken', 'description' => 'Gebruiker kan gepubliceerde roosters bekijken.', 'permissions' => [PermissionRegistry::ROSTER_VIEW]],
            'rooster_genereren' => ['label' => 'Rooster genereren', 'description' => 'Gebruiker kan roosters maken voor de gekozen school.', 'permissions' => [PermissionRegistry::ROSTER_GENERATE]],
            'rooster_bewerken' => ['label' => 'Rooster aanpassen', 'description' => 'Gebruiker kan conceptroosters aanpassen.', 'permissions' => [PermissionRegistry::ROSTER_EDIT]],
            'stamdata' => ['label' => 'Stamdata', 'description' => 'Schooljaren, klassen, vakken, lokalen en opleidingen beheren.', 'permissions' => [PermissionRegistry::SCHOOL_MANAGE]],
            'gebruikers' => ['label' => 'Gebruikersbeheer', 'description' => 'Gebruikers aanmaken, verwijderen en wachtwoorden resetten.', 'permissions' => [PermissionRegistry::USERS_MANAGE]],
            'auditlog' => ['label' => 'Auditlog', 'description' => 'Beveiligings- en beheeracties bekijken.', 'permissions' => [PermissionRegistry::AUDIT_VIEW]],
            'ziekte' => ['label' => 'Ziekte', 'description' => 'Afwezigheden beheren.', 'permissions' => [PermissionRegistry::ABSENCE_MANAGE]],
            'toetsplanning' => ['label' => 'Toetsplanning', 'description' => 'Toetsweken en toetsmomenten beheren.', 'permissions' => [PermissionRegistry::TEST_PLANNING_MANAGE]],
            'stage' => ['label' => 'Stage', 'description' => 'Stageprocessen beheren.', 'permissions' => [PermissionRegistry::INTERNSHIP_MANAGE]],
        ];

        if ($user->role === 'roostar_admin') {
            $options = [
                'platform' => ['label' => 'Roostar Admin', 'description' => 'Platformbeheer voor de hele omgeving.', 'permissions' => [PermissionRegistry::PLATFORM_MANAGE]],
                ...$options,
            ];
        }

        return array_filter($options, fn (array $option): bool => $this->canAssignAnyPermission($user, $option['permissions']));
    }

    private function canAssignAnyPermission(UserContext $user, array $permissions): bool
    {
        if ($user->role === 'roostar_admin' || $user->hasPermission(PermissionRegistry::USERS_MANAGE)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission((string) $permission)) {
                return true;
            }
        }

        return false;
    }

    private function canAssignPermission(UserContext $user, string $permission, string $schoolId): bool
    {
        if ($user->role === 'roostar_admin') {
            return true;
        }

        if ($permission === PermissionRegistry::PLATFORM_MANAGE) {
            return false;
        }

        return $user->hasPermission(PermissionRegistry::USERS_MANAGE, 'school', $schoolId)
            || $user->hasPermission($permission, 'school', $schoolId)
            || $user->hasPermission($permission);
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

        if ($currentRole === 'roostar_admin') {
            $roles = ['roostar_admin' => 'Roostar Admin', ...$roles];
        }

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
