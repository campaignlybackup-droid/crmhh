<?php

class AnnouncementModel
{
    public static function all(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT a.*, u.name as author_name 
            FROM announcements a
            JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public static function create(string $title, string $content, int $createdBy): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$title, $content, $createdBy]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
    }
}
