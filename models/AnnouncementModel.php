<?php

class AnnouncementModel
{
    public static function all(): array
    {
        return Database::all("
            SELECT a.*, u.name as author_name 
            FROM announcements a
            JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
        ");
    }

    public static function create(string $title, string $content, int $createdBy): int
    {
        Database::run("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)", [$title, $content, $createdBy]);
        return (int)Database::lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::run("DELETE FROM announcements WHERE id = ?", [$id]);
    }
    
    public static function update(int $id, string $title, string $content): void
    {
        Database::run("UPDATE announcements SET title = ?, content = ? WHERE id = ?", [$title, $content, $id]);
    }
}
