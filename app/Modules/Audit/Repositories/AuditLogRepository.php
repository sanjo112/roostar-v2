<?php

declare(strict_types=1);

namespace Roostar\Modules\Audit\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;

final class AuditLogRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function recentFor(UserContext $user, int $limit = 80): array
    {
        [$scopeSql, $params] = $this->scopeFor($user);
        $params['limit'] = max(10, min($limit, 200));

        $stmt = $this->db->prepare("
            SELECT
                a.action,
                a.entity_type,
                a.entity_id,
                a.metadata_json,
                a.ip_address,
                a.created_at,
                actor.email AS actor_email,
                actor.naam_encrypted AS actor_naam_encrypted,
                target.email AS target_email,
                target.naam_encrypted AS target_naam_encrypted
            FROM audit_log a
            LEFT JOIN users actor ON actor.id = a.user_id
            LEFT JOIN users target ON target.id = a.entity_id AND a.entity_type = 'user'
            LEFT JOIN scholen target_school ON target_school.id = target.school_id
            WHERE {$scopeSql}
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'limit' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();

        return array_map(fn (array $row): array => $this->present($row), $stmt->fetchAll());
    }

    private function scopeFor(UserContext $user): array
    {
        if ($user->role === 'roostar_admin') {
            return ['1 = 1', []];
        }

        $schoolIds = array_values(array_unique(array_filter(array_map(
            static fn (array $grant): ?string => ($grant['permission'] ?? null) === 'audit.view' && ($grant['scope_type'] ?? null) === 'school'
                ? (string) ($grant['scope_id'] ?? '')
                : null,
            $user->permissions,
        ))));

        if ($schoolIds === []) {
            return ['a.user_id = :user_id', ['user_id' => $user->id]];
        }

        $params = [];
        $actorPlaceholders = [];
        $targetPlaceholders = [];
        $targetSchoolPlaceholders = [];
        $metadataPlaceholders = [];

        foreach ($schoolIds as $index => $schoolId) {
            $actorKey = 'actor_school_id_' . $index;
            $targetKey = 'target_school_id_' . $index;
            $targetSchoolKey = 'target_school_scope_id_' . $index;
            $metadataKey = 'metadata_school_id_' . $index;

            $actorPlaceholders[] = ':' . $actorKey;
            $targetPlaceholders[] = ':' . $targetKey;
            $targetSchoolPlaceholders[] = ':' . $targetSchoolKey;
            $metadataPlaceholders[] = ':' . $metadataKey;

            $params[$actorKey] = $schoolId;
            $params[$targetKey] = $schoolId;
            $params[$targetSchoolKey] = $schoolId;
            $params[$metadataKey] = $schoolId;
        }

        return [
            "(
                actor.school_id IN (" . implode(', ', $actorPlaceholders) . ")
                OR target.school_id IN (" . implode(', ', $targetPlaceholders) . ")
                OR target_school.id IN (" . implode(', ', $targetSchoolPlaceholders) . ")
                OR JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json, '$.school_id')) IN (" . implode(', ', $metadataPlaceholders) . ")
            )",
            $params,
        ];
    }

    private function present(array $row): array
    {
        $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);

        return [
            'action' => (string) $row['action'],
            'action_label' => $this->actionLabel((string) $row['action']),
            'actor' => $this->nameOrEmail($row['actor_naam_encrypted'] ?? null, $row['actor_email'] ?? null),
            'target' => $this->nameOrEmail($row['target_naam_encrypted'] ?? null, $row['target_email'] ?? null),
            'metadata' => is_array($metadata) ? $metadata : [],
            'ip_address' => $row['ip_address'] ?: '-',
            'created_at' => $row['created_at'],
        ];
    }

    private function nameOrEmail(?string $encryptedName, ?string $email): string
    {
        if ($encryptedName) {
            try {
                return $this->encryptor->decrypt($encryptedName);
            } catch (\Throwable) {
                return $email ?: '[onleesbaar]';
            }
        }

        return $email ?: 'Systeem';
    }

    private function actionLabel(string $action): string
    {
        return [
            'auth.login.succeeded' => 'Login gelukt',
            'auth.login.failed' => 'Login mislukt',
            'auth.login.rate_limited' => 'Login geblokkeerd',
            'auth.password.changed' => 'Wachtwoord gewijzigd',
            'users.created' => 'Gebruiker aangemaakt',
            'users.create.denied' => 'Aanmaken geweigerd',
            'users.deactivated' => 'Gebruiker gedeactiveerd',
            'users.deactivated.denied' => 'Deactiveren geweigerd',
            'users.reactivated' => 'Gebruiker heractiveerd',
            'users.reactivated.denied' => 'Heractiveren geweigerd',
            'users.password_reset' => 'Wachtwoord gereset',
            'users.password_reset.denied' => 'Reset geweigerd',
        ][$action] ?? $action;
    }
}
