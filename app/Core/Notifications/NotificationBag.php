<?php

declare(strict_types=1);

namespace Roostar\Core\Notifications;

use Roostar\Core\Database\Connection;

final class NotificationBag
{
    private const SESSION_KEY = '_roostar_notifications';

    public static function success(string $message, string $title = 'Roostar'): void
    {
        self::push('success', $message, $title);
    }

    public static function error(string $message, string $title = 'Roostar'): void
    {
        self::push('error', $message, $title);
    }

    public static function warning(string $message, string $title = 'Roostar'): void
    {
        self::push('warning', $message, $title);
    }

    public static function info(string $message, string $title = 'Roostar'): void
    {
        self::push('info', $message, $title);
    }

    public static function pull(): array
    {
        $notifications = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        $notifications = is_array($notifications) ? $notifications : [];
        $userId = self::currentUserId();

        if ($userId) {
            try {
                $stored = (new NotificationRepository(Connection::get()))->unreadForUser($userId);
                $notifications = [
                    ...array_map(static fn (array $row): array => [
                        'id' => $row['id'],
                        'type' => $row['type'],
                        'title' => $row['title'],
                        'message' => $row['message'],
                    ], $stored),
                    ...$notifications,
                ];
            } catch (\Throwable) {
                return $notifications;
            }
        }

        return $notifications;
    }

    public static function recent(): array
    {
        $userId = self::currentUserId();

        if (!$userId) {
            return [];
        }

        try {
            return array_map(static fn (array $row): array => [
                'id' => $row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'read_at' => $row['read_at'],
                'created_at' => $row['created_at'],
                'is_read' => $row['read_at'] !== null,
            ], (new NotificationRepository(Connection::get()))->recentForUser($userId));
        } catch (\Throwable) {
            return [];
        }
    }

    private static function push(string $type, string $message, string $title): void
    {
        $userId = self::currentUserId();

        if ($userId) {
            try {
                (new NotificationRepository(Connection::get()))->create($userId, $type, $title, $message);
                return;
            } catch (\Throwable) {
                // Fall back to session notifications when the database is unavailable.
            }
        }

        $_SESSION[self::SESSION_KEY] ??= [];
        $_SESSION[self::SESSION_KEY][] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ];
    }

    private static function currentUserId(): ?string
    {
        return is_string($_SESSION['user_id'] ?? null) ? $_SESSION['user_id'] : null;
    }
}
