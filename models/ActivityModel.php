<?php

class ActivityModel
{
    public static function log(string $entityType, int $entityId, string $action, ?string $note = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        Database::run(
            'INSERT INTO activities (entity_type, entity_id, user_id, action, note, old_value, new_value, created_at)
             VALUES (?,?,?,?,?,?,?,NOW())',
            [$entityType, $entityId, Auth::id(), $action, $note, $oldValue, $newValue]
        );
    }

    public static function timeline(string $entityType, int $entityId): array
    {
        return Database::all(
            'SELECT a.*, u.name AS user_name FROM activities a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.created_at DESC',
            [$entityType, $entityId]
        );
    }
}
