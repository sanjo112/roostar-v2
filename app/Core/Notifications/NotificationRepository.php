<?php

declare(strict_types=1);

namespace Roostar\Core\Notifications;

use PDO;
use Roostar\Core\Support\Str;

final class NotificationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(string $userId, string $type, string $title, string $message): string
    {
        $id = Str::uuid();
        $stmt = $this->db->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, read_at, created_at)
            VALUES (:id, :user_id, :type, :title, :message, NULL, NOW())
        ");
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);

        return $id;
    }

    public function unreadForUser(string $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT id, type, title, message, created_at
            FROM notifications
            WHERE user_id = :user_id
              AND read_at IS NULL
              AND type = 'warning'
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', max(1, min($limit, 50)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recentForUser(string $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT id, type, title, message, read_at, created_at
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', max(1, min($limit, 25)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markRead(string $userId, array $ids = []): void
    {
        if ($ids === []) {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET read_at = NOW()
                WHERE user_id = :user_id
                  AND read_at IS NULL
            ");
            $stmt->execute(['user_id' => $userId]);
            return;
        }

        $ids = array_values(array_filter($ids, static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($ids === []) {
            return;
        }

        $placeholders = [];
        $params = ['user_id' => $userId];

        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET read_at = NOW()
            WHERE user_id = :user_id
              AND read_at IS NULL
              AND id IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);
    }
}
