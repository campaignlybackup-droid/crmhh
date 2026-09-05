<?php

class Notifier
{
    public static function send(int $userId, string $type, string $title, ?string $message = null, ?string $relatedType = null, ?int $relatedId = null): void
    {
        if (!$userId) return;
        Database::run(
            'INSERT INTO notifications (user_id, type, title, message, related_type, related_id, created_at)
             VALUES (?,?,?,?,?,?,NOW())',
            [$userId, $type, $title, $message, $relatedType, $relatedId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int)Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function recent(int $userId, int $limit = 10): array
    {
        return Database::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::run('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }

    public static function markRead(int $userId, int $id): void
    {
        Database::run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?', [$userId, $id]);
    }
}
