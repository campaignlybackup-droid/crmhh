<?php

class ContentCalendarModel
{
    public static function all(?int $clientId = null, ?int $assignedTo = null, ?string $contentType = null): array
    {
        $params = [];
        $sql = 'SELECT cc.*, c.name AS client_name, s.name AS service_name, sub.name AS subcategory_name, 
                       u.name AS assignee_name, u_ed.name AS editor_name, u_mgr.name AS manager_name 
                FROM content_calendar cc 
                JOIN clients c ON c.id = cc.client_id
                LEFT JOIN services s ON s.id = cc.service_id
                LEFT JOIN service_subcategories sub ON sub.id = cc.subcategory_id
                LEFT JOIN users u ON u.id = cc.assigned_to
                LEFT JOIN users u_ed ON u_ed.id = cc.editor_checked_by
                LEFT JOIN users u_mgr ON u_mgr.id = cc.manager_reviewed_by
                WHERE 1=1';

        if ($clientId) {
            $sql .= ' AND cc.client_id = ?';
            $params[] = $clientId;
        }
        if ($assignedTo) {
            $sql .= ' AND cc.assigned_to = ?';
            $params[] = $assignedTo;
        }
        if ($contentType && in_array($contentType, ['reel', 'post', 'carousel', 'story'], true)) {
            $sql .= ' AND cc.content_type = ?';
            $params[] = $contentType;
        }

        $sql .= ' ORDER BY cc.post_date ASC, cc.id DESC';
        return Database::all($sql, $params);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT cc.*, c.name AS client_name, s.name AS service_name, sub.name AS subcategory_name, 
                                     u.name AS assignee_name, u_ed.name AS editor_name, u_mgr.name AS manager_name 
                              FROM content_calendar cc 
                              JOIN clients c ON c.id = cc.client_id
                              LEFT JOIN services s ON s.id = cc.service_id
                              LEFT JOIN service_subcategories sub ON sub.id = cc.subcategory_id
                              LEFT JOIN users u ON u.id = cc.assigned_to
                              LEFT JOIN users u_ed ON u_ed.id = cc.editor_checked_by
                              LEFT JOIN users u_mgr ON u_mgr.id = cc.manager_reviewed_by
                              WHERE cc.id = ?', [$id]);
    }

    public static function editorCheck(int $id, bool $passed, ?string $notes = null): void
    {
        $item = self::find($id);
        if (!$item) return;

        if ($passed) {
            Database::run(
                "UPDATE content_calendar SET status = 'manager_review', editor_checked_by = ?, editor_checked_at = NOW(), rectification_notes = NULL WHERE id = ?",
                [Auth::id(), $id]
            );
            // Notify managers and founder that editor has verified, ready for manager final review
            $managers = Database::all("SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug IN ('founder', 'manager') AND u.status = 'active'");
            foreach ($managers as $m) {
                Notifier::send(
                    (int)$m['id'],
                    'content_manager_review',
                    'Double Check: Content Checked by Editor (' . Auth::name() . ')',
                    "Content '{$item['title']}' for {$item['client_name']} has been verified by editor and is now waiting for Manager review (Manav / Manager).",
                    'content_calendar',
                    $id
                );
            }
            AuditLog::record('editor_checked', 'content_calendar', $id, 'pending_editor_check', 'manager_review');
        } else {
            Database::run(
                "UPDATE content_calendar SET status = 'needs_rectification', editor_checked_by = ?, editor_checked_at = NOW(), rectification_notes = ? WHERE id = ?",
                [Auth::id(), $notes, $id]
            );
            if (!empty($item['assigned_to'])) {
                Notifier::send(
                    (int)$item['assigned_to'],
                    'content_needs_rectification',
                    '⚠️ Issues Flagged by Editor on ' . $item['title'],
                    "Editor " . Auth::name() . " flagged issues for rectification: " . $notes,
                    'content_calendar',
                    $id
                );
            }
            AuditLog::record('editor_flagged_issues', 'content_calendar', $id, 'pending_editor_check', 'needs_rectification');
        }
    }

    public static function managerReview(int $id, bool $approved, ?string $notes = null): void
    {
        $item = self::find($id);
        if (!$item) return;

        if ($approved) {
            Database::run(
                "UPDATE content_calendar SET status = 'scheduled', manager_reviewed_by = ?, manager_reviewed_at = NOW(), rectification_notes = NULL WHERE id = ?",
                [Auth::id(), $id]
            );
            if (!empty($item['assigned_to'])) {
                Notifier::send(
                    (int)$item['assigned_to'],
                    'content_approved',
                    '✓ Content Final Approved by ' . Auth::name(),
                    "Content '{$item['title']}' has been reviewed & approved by manager, scheduled for posting.",
                    'content_calendar',
                    $id
                );
            }
            AuditLog::record('manager_approved', 'content_calendar', $id, 'manager_review', 'scheduled');
        } else {
            Database::run(
                "UPDATE content_calendar SET status = 'needs_rectification', manager_reviewed_by = ?, manager_reviewed_at = NOW(), rectification_notes = ? WHERE id = ?",
                [Auth::id(), $notes, $id]
            );
            if (!empty($item['assigned_to'])) {
                Notifier::send(
                    (int)$item['assigned_to'],
                    'content_needs_rectification',
                    '⚠️ Issues Flagged in Manager Review: ' . $item['title'],
                    "Manager " . Auth::name() . " requested rectification: " . $notes,
                    'content_calendar',
                    $id
                );
            }
            AuditLog::record('manager_flagged_issues', 'content_calendar', $id, 'manager_review', 'needs_rectification');
        }
    }

    public static function create(
        int $clientId, 
        ?int $serviceId, 
        ?int $subcategoryId, 
        string $postDate, 
        string $title, 
        ?string $content, 
        string $status, 
        ?int $assignedTo,
        string $contentType = 'reel',
        ?string $driveLink = null
    ): int {
        // Auto-match service and subcategory if not supplied
        if (!$serviceId || !$subcategoryId) {
            [$autoSvcId, $autoSubId] = self::matchServiceAndSubcategory($clientId, $contentType);
            if (!$serviceId) $serviceId = $autoSvcId;
            if (!$subcategoryId) $subcategoryId = $autoSubId;
        }

        $completedAt = in_array($status, ['published', 'completed'], true) ? date('Y-m-d H:i:s') : null;

        Database::run(
            'INSERT INTO content_calendar (client_id, service_id, subcategory_id, content_type, post_date, title, content, drive_link, status, assigned_to, completed_at, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [$clientId, $serviceId ?: null, $subcategoryId ?: null, $contentType, $postDate, $title, $content, $driveLink ?: null, $status, $assignedTo ?: null, $completedAt, Auth::id()]
        );
        $id = (int)Database::lastInsertId();
        
        self::handleStatusChange(null, $status, $clientId, $serviceId, $subcategoryId, $contentType);
        AuditLog::record('create', 'content_calendar', $id, null, "[$contentType] $title");
        
        return $id;
    }

    public static function update(
        int $id, 
        string $postDate, 
        string $title, 
        ?string $content, 
        string $status, 
        ?int $assignedTo,
        string $contentType = 'reel',
        ?string $driveLink = null
    ): void {
        $existing = self::find($id);
        if (!$existing) return;

        $completedAt = in_array($status, ['published', 'completed'], true) 
            ? ($existing['completed_at'] ?? date('Y-m-d H:i:s')) 
            : null;

        Database::run(
            'UPDATE content_calendar SET post_date=?, title=?, content=?, drive_link=?, status=?, assigned_to=?, content_type=?, completed_at=? WHERE id=?',
            [$postDate, $title, $content, $driveLink ?: null, $status, $assignedTo ?: null, $contentType, $completedAt, $id]
        );
        
        if ($existing['status'] !== $status) {
            self::handleStatusChange(
                $existing['status'], 
                $status, 
                (int)$existing['client_id'], 
                $existing['service_id'] ? (int)$existing['service_id'] : null, 
                $existing['subcategory_id'] ? (int)$existing['subcategory_id'] : null,
                $contentType
            );
        }
    }

    public static function toggleStatus(int $id, ?string $forceStatus = null): array
    {
        $existing = self::find($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        $oldStatus = $existing['status'];
        if ($forceStatus) {
            $newStatus = $forceStatus;
        } else {
            // Toggle between published and scheduled
            $newStatus = in_array($oldStatus, ['published', 'completed'], true) ? 'scheduled' : 'published';
        }

        $completedAt = in_array($newStatus, ['published', 'completed'], true) ? date('Y-m-d H:i:s') : null;

        Database::run(
            'UPDATE content_calendar SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?',
            [$newStatus, $completedAt, $id]
        );

        self::handleStatusChange(
            $oldStatus, 
            $newStatus, 
            (int)$existing['client_id'], 
            $existing['service_id'] ? (int)$existing['service_id'] : null, 
            $existing['subcategory_id'] ? (int)$existing['subcategory_id'] : null,
            $existing['content_type'] ?? 'reel'
        );

        AuditLog::record('status_change', 'content_calendar', $id, $oldStatus, $newStatus);

        return [
            'success' => true, 
            'id' => $id, 
            'old_status' => $oldStatus, 
            'new_status' => $newStatus,
            'is_completed' => in_array($newStatus, ['published', 'completed'], true)
        ];
    }

    public static function delete(int $id): void
    {
        $existing = self::find($id);
        if ($existing) {
            if (in_array($existing['status'], ['published', 'completed'], true)) {
                self::handleStatusChange(
                    'published', 
                    'draft', 
                    (int)$existing['client_id'], 
                    $existing['service_id'] ? (int)$existing['service_id'] : null, 
                    $existing['subcategory_id'] ? (int)$existing['subcategory_id'] : null,
                    $existing['content_type'] ?? 'reel'
                );
            }
            Database::run('DELETE FROM content_calendar WHERE id = ?', [$id]);
            AuditLog::record('delete', 'content_calendar', $id);
        }
    }

    public static function forClientGrouped(int $clientId): array
    {
        $items = self::all($clientId);
        $reels = [];
        $posts = [];
        $others = [];

        foreach ($items as $item) {
            $type = strtolower($item['content_type'] ?? 'reel');
            if ($type === 'reel') {
                $reels[] = $item;
            } elseif (in_array($type, ['post', 'static', 'graphic'], true)) {
                $posts[] = $item;
            } else {
                $others[] = $item;
            }
        }

        // Get required quantities from client_service_quantities
        $reqReels = (int)Database::scalar(
            "SELECT SUM(csq.quantity_required) FROM client_service_quantities csq
             JOIN client_services cs ON cs.id = csq.client_service_id
             JOIN service_subcategories sub ON sub.id = csq.subcategory_id
             WHERE cs.client_id = ? AND cs.deleted_at IS NULL AND (sub.name LIKE '%reel%' OR sub.name LIKE '%video%')",
            [$clientId]
        );

        $reqPosts = (int)Database::scalar(
            "SELECT SUM(csq.quantity_required) FROM client_service_quantities csq
             JOIN client_services cs ON cs.id = csq.client_service_id
             JOIN service_subcategories sub ON sub.id = csq.subcategory_id
             WHERE cs.client_id = ? AND cs.deleted_at IS NULL AND (sub.name LIKE '%post%' OR sub.name LIKE '%static%' OR sub.name LIKE '%graphic%' OR sub.name LIKE '%carousel%')",
            [$clientId]
        );

        $compReels = count(array_filter($reels, fn($r) => in_array($r['status'], ['published', 'completed'], true)));
        $compPosts = count(array_filter($posts, fn($p) => in_array($p['status'], ['published', 'completed'], true)));

        return [
            'reels' => $reels,
            'posts' => $posts,
            'others' => $others,
            'reels_required' => $reqReels,
            'reels_completed' => $compReels,
            'posts_required' => $reqPosts,
            'posts_completed' => $compPosts,
        ];
    }

    public static function clientsWithDeliverables(): array
    {
        $clients = Database::all('SELECT id, name, company FROM clients WHERE deleted_at IS NULL ORDER BY name ASC');
        $result = [];

        foreach ($clients as $c) {
            $grouped = self::forClientGrouped((int)$c['id']);
            $totalCount = count($grouped['reels']) + count($grouped['posts']) + count($grouped['others']);
            if ($totalCount > 0 || $grouped['reels_required'] > 0 || $grouped['posts_required'] > 0) {
                $c['reels'] = $grouped['reels'];
                $c['posts'] = $grouped['posts'];
                $c['reels_required'] = $grouped['reels_required'];
                $c['reels_completed'] = $grouped['reels_completed'];
                $c['posts_required'] = $grouped['posts_required'];
                $c['posts_completed'] = $grouped['posts_completed'];
                $result[] = $c;
            }
        }

        return $result;
    }

    private static function matchServiceAndSubcategory(int $clientId, string $contentType): array
    {
        $searchKey = ($contentType === 'reel') ? '%reel%' : '%post%';
        $row = Database::one(
            "SELECT csq.client_service_id, cs.service_id, csq.subcategory_id 
             FROM client_service_quantities csq
             JOIN client_services cs ON cs.id = csq.client_service_id
             JOIN service_subcategories sub ON sub.id = csq.subcategory_id
             WHERE cs.client_id = ? AND cs.status = 'active' AND cs.deleted_at IS NULL AND sub.name LIKE ?
             LIMIT 1",
            [$clientId, $searchKey]
        );

        if ($row) {
            return [(int)$row['service_id'], (int)$row['subcategory_id']];
        }

        // Fallback: match any active client service
        $cs = Database::one("SELECT id, service_id FROM client_services WHERE client_id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1", [$clientId]);
        if ($cs) {
            return [(int)$cs['service_id'], null];
        }

        return [null, null];
    }

    private static function handleStatusChange(?string $oldStatus, string $newStatus, int $clientId, ?int $serviceId, ?int $subcategoryId, string $contentType = 'reel'): void
    {
        $wasDone = in_array($oldStatus, ['published', 'completed'], true);
        $isDone = in_array($newStatus, ['published', 'completed'], true);

        if ($wasDone && !$isDone) {
            self::adjustQuantityCompleted($clientId, $serviceId, $subcategoryId, -1, $contentType);
        } elseif (!$wasDone && $isDone) {
            self::adjustQuantityCompleted($clientId, $serviceId, $subcategoryId, 1, $contentType);
        }
    }

    private static function adjustQuantityCompleted(int $clientId, ?int $serviceId, ?int $subcategoryId, int $amount, string $contentType = 'reel'): void
    {
        // 1. If serviceId is not given, find the first active client service
        $cs = null;
        if ($serviceId) {
            $cs = Database::one("SELECT id, quantity_completed FROM client_services WHERE client_id = ? AND service_id = ? AND status = 'active' AND deleted_at IS NULL", [$clientId, $serviceId]);
        }
        if (!$cs) {
            $cs = Database::one("SELECT id, quantity_completed, service_id FROM client_services WHERE client_id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1", [$clientId]);
        }
        if (!$cs) return;

        $csId = (int)$cs['id'];

        // 2. Adjust subcategory quantity
        if ($subcategoryId) {
            $csq = Database::one("SELECT id, quantity_completed FROM client_service_quantities WHERE client_service_id = ? AND subcategory_id = ?", [$csId, $subcategoryId]);
            if ($csq) {
                $newQty = max(0, (int)$csq['quantity_completed'] + $amount);
                Database::run("UPDATE client_service_quantities SET quantity_completed = ? WHERE id = ?", [$newQty, $csq['id']]);
            }
        } else {
            // Find subcategory by contentType keyword
            $kw = ($contentType === 'reel') ? '%reel%' : '%post%';
            $csq = Database::one(
                "SELECT csq.id, csq.quantity_completed FROM client_service_quantities csq
                 JOIN service_subcategories sub ON sub.id = csq.subcategory_id
                 WHERE csq.client_service_id = ? AND sub.name LIKE ? LIMIT 1",
                [$csId, $kw]
            );
            if ($csq) {
                $newQty = max(0, (int)$csq['quantity_completed'] + $amount);
                Database::run("UPDATE client_service_quantities SET quantity_completed = ? WHERE id = ?", [$newQty, $csq['id']]);
            }
        }
        
        // 3. Update the global quantity completed for the service
        $newGlobalQty = max(0, (int)$cs['quantity_completed'] + $amount);
        Database::run("UPDATE client_services SET quantity_completed = ? WHERE id = ?", [$newGlobalQty, $csId]);
    }
}
