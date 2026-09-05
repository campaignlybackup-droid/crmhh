<?php

class AuditLog
{
    public static function record(string $action, string $entityType, ?int $entityId, ?string $oldValue = null, ?string $newValue = null): void
    {
        try {
            Database::run(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address, created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())',
                [Auth::id(), $action, $entityType, $entityId, $oldValue, $newValue, $_SERVER['REMOTE_ADDR'] ?? null]
            );
        } catch (Throwable $e) {
            app_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
