<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Services;

use PDO;
use Roostar\Core\Support\Str;

final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(private readonly PDO $db)
    {
    }

    public function tooManyAttempts(string $email, string $ipAddress): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM login_attempts
            WHERE email_hash = :email_hash
              AND ip_address = :ip_address
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL " . self::WINDOW_MINUTES . " MINUTE)
        ");
        $stmt->execute([
            'email_hash' => Str::searchHash($email),
            'ip_address' => $ipAddress,
        ]);

        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public function recordFailedAttempt(string $email, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (id, email_hash, ip_address, attempted_at)
            VALUES (:id, :email_hash, :ip_address, NOW())
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'email_hash' => Str::searchHash($email),
            'ip_address' => $ipAddress,
        ]);
    }

    public function clear(string $email, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts
            WHERE email_hash = :email_hash
              AND ip_address = :ip_address
        ");
        $stmt->execute([
            'email_hash' => Str::searchHash($email),
            'ip_address' => $ipAddress,
        ]);
    }
}

