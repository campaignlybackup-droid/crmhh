<?php

class ContentCalendarModel
{
    public static function all(?int $clientId = null, ?int $assignedTo = null): array
    {
        $params = [];
        $sql = 'SELECT cc.*, c.name AS client_name, s.name AS service_name, sub.name AS subcategory_name, u.name AS assignee_name 
                FROM content_calendar cc 
                JOIN clients c ON c.id = cc.client_id
                JOIN services s ON s.id = cc.service_id
                LEFT JOIN service_subcategories sub ON sub.id = cc.subcategory_id
                LEFT JOIN users u ON u.id = cc.assigned_to
                WHERE 1=1';

        if ($clientId) {
            $sql .= ' AND cc.client_id = ?';
            $params[] = $clientId;
        }
        if ($assignedTo) {
            $sql .= ' AND cc.assigned_to = ?';
            $params[] = $assignedTo;
        }

        $sql .= ' ORDER BY cc.post_date ASC';
        return Database::all($sql, $params);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT cc.*, c.name AS client_name, s.name AS service_name, sub.name AS subcategory_name, u.name AS assignee_name 
                              FROM content_calendar cc 
                              JOIN clients c ON c.id = cc.client_id
                              JOIN services s ON s.id = cc.service_id
                              LEFT JOIN service_subcategories sub ON sub.id = cc.subcategory_id
                              LEFT JOIN users u ON u.id = cc.assigned_to
                              WHERE cc.id = ?', [$id]);
    }

    public static function create(int $clientId, int $serviceId, ?int $subcategoryId, string $postDate, string $title, ?string $content, string $status, ?int $assignedTo): int
    {
        Database::run(
            'INSERT INTO content_calendar (client_id, service_id, subcategory_id, post_date, title, content, status, assigned_to, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())',
            [$clientId, $serviceId, $subcategoryId ?: null, $postDate, $title, $content, $status, $assignedTo ?: null, Auth::id()]
        );
        $id = (int)Database::lastInsertId();
        
        self::handleStatusChange(null, $status, $clientId, $serviceId, $subcategoryId);
        AuditLog::record('create', 'content_calendar', $id, null, $title);
        
        return $id;
    }

    public static function update(int $id, string $postDate, string $title, ?string $content, string $status, ?int $assignedTo): void
    {
        $existing = self::find($id);
        if (!$existing) return;

        Database::run(
            'UPDATE content_calendar SET post_date=?, title=?, content=?, status=?, assigned_to=? WHERE id=?',
            [$postDate, $title, $content, $status, $assignedTo ?: null, $id]
        );
        
        if ($existing['status'] !== $status) {
            self::handleStatusChange($existing['status'], $status, (int)$existing['client_id'], (int)$existing['service_id'], $existing['subcategory_id'] ? (int)$existing['subcategory_id'] : null);
        }
    }

    public static function delete(int $id): void
    {
        $existing = self::find($id);
        if ($existing) {
            if ($existing['status'] === 'published') {
                self::handleStatusChange('published', 'draft', (int)$existing['client_id'], (int)$existing['service_id'], $existing['subcategory_id'] ? (int)$existing['subcategory_id'] : null);
            }
            Database::run('DELETE FROM content_calendar WHERE id = ?', [$id]);
            AuditLog::record('delete', 'content_calendar', $id);
        }
    }

    private static function handleStatusChange(?string $oldStatus, string $newStatus, int $clientId, int $serviceId, ?int $subcategoryId): void
    {
        if ($oldStatus === 'published' && $newStatus !== 'published') {
            // Decrement
            self::adjustQuantityCompleted($clientId, $serviceId, $subcategoryId, -1);
        } elseif ($oldStatus !== 'published' && $newStatus === 'published') {
            // Increment
            self::adjustQuantityCompleted($clientId, $serviceId, $subcategoryId, 1);
        }
    }

    private static function adjustQuantityCompleted(int $clientId, int $serviceId, ?int $subcategoryId, int $amount): void
    {
        // Find the active client_service
        $cs = Database::one("SELECT id, quantity_completed FROM client_services WHERE client_id = ? AND service_id = ? AND status = 'active' AND deleted_at IS NULL", [$clientId, $serviceId]);
        if (!$cs) return;

        $csId = (int)$cs['id'];

        if ($subcategoryId) {
            // Adjust the specific subcategory quantity
            $csq = Database::one("SELECT id, quantity_completed FROM client_service_quantities WHERE client_service_id = ? AND subcategory_id = ?", [$csId, $subcategoryId]);
            if ($csq) {
                $newQty = max(0, (int)$csq['quantity_completed'] + $amount);
                Database::run("UPDATE client_service_quantities SET quantity_completed = ? WHERE id = ?", [$newQty, $csq['id']]);
            }
        }
        
        // Also update the global quantity completed for the service
        $newGlobalQty = max(0, (int)$cs['quantity_completed'] + $amount);
        Database::run("UPDATE client_services SET quantity_completed = ? WHERE id = ?", [$newGlobalQty, $csId]);
    }
}
