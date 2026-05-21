<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

use PDO;
use Roostar\Core\Support\Str;

final class SecurityTokenRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function store(?string $userId, string $purpose, SecurityToken $token, string $expiresAt): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO security_tokens (id, user_id, purpose, selector, token_hash, expires_at, created_at)
            VALUES (:id, :user_id, :purpose, :selector, :token_hash, :expires_at, NOW())
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'purpose' => $purpose,
            'selector' => $token->selector,
            'token_hash' => SecurityToken::hash($token->plainToken),
            'expires_at' => $expiresAt,
        ]);
    }

    public function consume(string $purpose, string $publicToken): ?array
    {
        [$selector, $plainToken] = array_pad(explode(':', $publicToken, 2), 2, '');

        if ($selector === '' || $plainToken === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM security_tokens
            WHERE selector = :selector
              AND purpose = :purpose
              AND consumed_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['selector' => $selector, 'purpose' => $purpose]);
        $row = $stmt->fetch();

        if (!is_array($row) || !hash_equals((string) $row['token_hash'], SecurityToken::hash($plainToken))) {
            return null;
        }

        $this->db
            ->prepare("UPDATE security_tokens SET consumed_at = NOW() WHERE id = :id")
            ->execute(['id' => $row['id']]);

        return $row;
    }
}

