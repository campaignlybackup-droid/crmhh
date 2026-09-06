<?php

class LeadModel
{
    public static function statuses(): array
    {
        return Database::all('SELECT * FROM lead_statuses ORDER BY sort_order ASC');
    }

    public static function defaultStatusId(): int
    {
        $row = Database::one("SELECT id FROM lead_statuses WHERE is_default = 1 LIMIT 1");
        return $row ? (int)$row['id'] : 1;
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT l.*, ls.name AS status_name, ls.slug AS status_slug, ls.color AS status_color,
                    u.name AS assigned_name, c.name AS created_by_name
             FROM leads l
             JOIN lead_statuses ls ON ls.id = l.status_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             LEFT JOIN users c ON c.id = l.created_by
             WHERE l.id = ? AND l.deleted_at IS NULL',
            [$id]
        );
    }

    public static function canAccess(int $leadId, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        $isFounder = Auth::hasRole('founder');
        if ($isFounder) return true;
        
        $lead = Database::one('SELECT assigned_user_id, created_by, folder_id FROM leads WHERE id = ? AND deleted_at IS NULL', [$leadId]);
        if (!$lead) return false;
        
        if ($lead['folder_id']) {
            $hasAccess = (int)Database::scalar('SELECT COUNT(*) FROM lead_folder_users WHERE folder_id = ? AND user_id = ?', [$lead['folder_id'], $userId]);
            if ($hasAccess > 0) return true;
        }
        
        $ownerId = (int)$lead['assigned_user_id'] ?: (int)$lead['created_by'];
        if ($ownerId === $userId) return true;
        
        $isManager = Auth::hasRole('manager');
        if ($isManager && !$lead['folder_id']) {
            $isOwnerFounder = (int)Database::scalar('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = ?', [$ownerId, 'founder']);
            return $isOwnerFounder === 0;
        }
        
        return false;
    }

    public static function getCustomFolders(int $userId): array
    {
        $isFounder = Auth::hasRole('founder');
        if ($isFounder) {
            return Database::all('SELECT lf.*, (SELECT COUNT(*) FROM leads l WHERE l.folder_id = lf.id AND l.deleted_at IS NULL) as lead_count FROM lead_folders lf ORDER BY lf.name');
        } else {
            return Database::all('SELECT lf.*, (SELECT COUNT(*) FROM leads l WHERE l.folder_id = lf.id AND l.deleted_at IS NULL) as lead_count FROM lead_folders lf JOIN lead_folder_users lfu ON lfu.folder_id = lf.id WHERE lfu.user_id = ? ORDER BY lf.name', [$userId]);
        }
    }

    public static function getFolderStats(int $userId): array
    {
        $isFounder = Auth::hasRole('founder');
        $isManager = Auth::hasRole('manager');
        if (!$isFounder && !$isManager) return [];
        
        $sql = "SELECT u.id, u.name, COUNT(l.id) AS lead_count 
                FROM users u 
                JOIN user_roles ur ON ur.user_id = u.id
                JOIN roles r ON r.id = ur.role_id
                LEFT JOIN leads l ON l.assigned_user_id = u.id AND l.deleted_at IS NULL
                WHERE u.status = 'active' AND u.deleted_at IS NULL
                  AND r.slug IN ('manager', 'sales') ";
                
        if (!$isFounder && $isManager) {
            $sql .= " AND u.id NOT IN (SELECT ur2.user_id FROM user_roles ur2 JOIN roles r2 ON r2.id = ur2.role_id WHERE r2.slug = 'founder')";
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.name";
        return Database::all($sql);
    }

    public static function getDashboardStats(array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $isFounder = Auth::hasRole('founder');
        $isManager = Auth::hasRole('manager');
        
        $where = ['l.deleted_at IS NULL'];
        $params = [];
        
        $assignedUserId = $filters['assigned_user_id'] ?? '';
        $folderId = $filters['folder_id'] ?? '';
        
        if ($folderId !== '') {
            $where[] = "l.folder_id = ?";
            $params[] = $folderId;
            // Access is checked at the controller level or we can enforce it here
            if (!$isFounder) {
                $where[] = "EXISTS (SELECT 1 FROM lead_folder_users lfu WHERE lfu.folder_id = l.folder_id AND lfu.user_id = ?)";
                $params[] = $userId;
            }
        } else {
            $where[] = "l.folder_id IS NULL"; // Regular assignments
            
            if ($assignedUserId === 'all') {
                $where[] = "l.assigned_user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')";
            } elseif ($assignedUserId !== '') {
                $where[] = "l.assigned_user_id = ?";
                $params[] = $assignedUserId;
            } elseif (!$isFounder && !$isManager) {
                $where[] = 'l.assigned_user_id = ?';
                $params[] = $userId;
            } elseif (!$isFounder && $isManager) {
                 $where[] = "l.assigned_user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')";
            }
        }

        $baseWhere = implode(' AND ', $where);
        
        $dateWhere = "";
        $dateParams = [];
        if (!empty($filters['date_from'])) {
            $dateWhere .= ' AND DATE(l.created_at) >= ?';
            $dateParams[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $dateWhere .= ' AND DATE(l.created_at) <= ?';
            $dateParams[] = $filters['date_to'];
        }
        
        $mergedParams = array_merge($params, $dateParams);
        
        // Stats queries
        $contacted = (int)Database::scalar("SELECT COUNT(*) FROM leads l JOIN lead_statuses ls ON ls.id = l.status_id WHERE $baseWhere $dateWhere AND ls.slug = 'contacted' AND DATE(l.updated_at) = CURDATE()", $mergedParams);
        $followups = (int)Database::scalar("SELECT COUNT(*) FROM leads l WHERE $baseWhere $dateWhere AND l.next_followup_date = CURDATE()", $mergedParams);
        $pending = (int)Database::scalar("SELECT COUNT(*) FROM leads l JOIN lead_statuses ls ON ls.id = l.status_id WHERE $baseWhere $dateWhere AND ls.slug = 'new'", $mergedParams);
        $missed = (int)Database::scalar("SELECT COUNT(*) FROM leads l WHERE $baseWhere $dateWhere AND l.next_followup_date < CURDATE()", $mergedParams);
        
        return [
            'contacted' => $contacted,
            'followups' => $followups,
            'pending' => $pending,
            'missed' => $missed
        ];
    }

    public static function paginate(int $page, int $perPage, array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $where = ['l.deleted_at IS NULL'];
        $params = [];
        
        $isFounder = Auth::hasRole('founder');
        $isManager = Auth::hasRole('manager');
        
        $assignedUserId = $filters['assigned_user_id'] ?? '';
        $folderId = $filters['folder_id'] ?? '';
        
        if ($folderId !== '') {
            $where[] = "l.folder_id = ?";
            $params[] = $folderId;
            if (!$isFounder) {
                $where[] = "EXISTS (SELECT 1 FROM lead_folder_users lfu WHERE lfu.folder_id = l.folder_id AND lfu.user_id = ?)";
                $params[] = $userId;
            }
        } else {
            $where[] = "l.folder_id IS NULL";
            if ($assignedUserId === 'all') {
                 $where[] = "l.assigned_user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')";
            } elseif ($assignedUserId !== '') {
                 $where[] = 'l.assigned_user_id = ?'; 
                 $params[] = $assignedUserId; 
            } elseif (!$isFounder && !$isManager) {
                $where[] = 'l.assigned_user_id = ?';
                $params[] = $userId;
            } elseif (!$isFounder && $isManager) {
                $where[] = "l.assigned_user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')";
            }
        }

        if (!empty($filters['status_id'])) { $where[] = 'l.status_id = ?'; $params[] = $filters['status_id']; }
        if (!empty($filters['assigned_user_id'])) { $where[] = 'l.assigned_user_id = ?'; $params[] = $filters['assigned_user_id']; }
        if (!empty($filters['source'])) { $where[] = 'l.source = ?'; $params[] = $filters['source']; }
        if (!empty($filters['followup']) && $filters['followup'] === 'today') { $where[] = 'l.next_followup_date = CURDATE()'; }
        if (!empty($filters['followup']) && $filters['followup'] === 'overdue') { $where[] = 'l.next_followup_date < CURDATE()'; }
        if (!empty($filters['followup']) && $filters['followup'] === 'upcoming') { $where[] = 'l.next_followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'; }
        if (!empty($filters['date_from'])) { $where[] = 'DATE(l.created_at) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $where[] = 'DATE(l.created_at) <= ?'; $params[] = $filters['date_to']; }
        if (!empty($filters['search'])) {
            $where[] = '(l.name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR l.lead_code LIKE ? OR l.company LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM leads l WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT l.*, ls.name AS status_name, ls.color AS status_color, u.name AS assigned_name
             FROM leads l
             JOIN lead_statuses ls ON ls.id = l.status_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             WHERE $whereSql ORDER BY l.created_at DESC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }

    public static function findByPhoneOrEmail(?string $phone, ?string $email): ?array
    {
        $normPhone = normalize_phone($phone);
        if ($normPhone !== '') {
            $row = Database::one(
                "SELECT * FROM leads WHERE deleted_at IS NULL AND RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ? LIMIT 1",
                [$normPhone]
            );
            if ($row) return $row;
        }
        if (valid_email($email)) {
            $row = Database::one('SELECT * FROM leads WHERE deleted_at IS NULL AND LOWER(email) = ? LIMIT 1', [strtolower(trim($email))]);
            if ($row) return $row;
        }
        return null;
    }

    public static function create(array $data): int
    {
        $code = next_code('leads', 'lead_code', 'LD');
        Database::run(
            'INSERT INTO leads (lead_code, name, phone, email, company, source, status_id, assigned_user_id, created_by, next_followup_date, notes, folder_id, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $code, $data['name'], $data['phone'] ?: null, $data['email'] ?: null, $data['company'] ?: null,
                $data['source'] ?: null, $data['status_id'] ?: self::defaultStatusId(), $data['assigned_user_id'] ?: null,
                $data['created_by'] ?? Auth::id(), $data['next_followup_date'] ?: null, $data['notes'] ?: null,
                $data['folder_id'] ?? null
            ]
        );
        $id = (int)Database::lastInsertId();
        ActivityModel::log('lead', $id, 'created', 'Lead created' . (!empty($data['assigned_user_id']) ? ' and assigned' : ''));
        if (!empty($data['assigned_user_id'])) {
            Notifier::send((int)$data['assigned_user_id'], 'lead_assigned', 'New lead assigned: ' . $data['name'], "Lead $code has been assigned to you.", 'lead', $id);
        }
        AuditLog::record('create', 'lead', $id, null, $code);
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        Database::run(
            'UPDATE leads SET name=?, phone=?, email=?, company=?, source=?, notes=?, next_followup_date=? WHERE id=?',
            [$data['name'], $data['phone'] ?: null, $data['email'] ?: null, $data['company'] ?: null, $data['source'] ?: null, $data['notes'] ?: null, $data['next_followup_date'] ?: null, $id]
        );

        if (!empty($data['status_id']) && (int)$data['status_id'] !== (int)$before['status_id']) {
            self::changeStatus($id, (int)$data['status_id']);
        }
        AuditLog::record('update', 'lead', $id);
    }

    public static function changeStatus(int $id, int $statusId, ?string $note = null): void
    {
        $before = Database::one('SELECT status_id FROM leads WHERE id = ?', [$id]);
        $oldName = Database::scalar('SELECT name FROM lead_statuses WHERE id = ?', [$before['status_id']]);
        $newName = Database::scalar('SELECT name FROM lead_statuses WHERE id = ?', [$statusId]);
        Database::run('UPDATE leads SET status_id = ? WHERE id = ?', [$statusId, $id]);
        ActivityModel::log('lead', $id, 'status_changed', $note, $oldName, $newName);
        AuditLog::record('status_change', 'lead', $id, $oldName, $newName);
    }

    public static function assign(int $id, int $newUserId, ?string $note = null): void
    {
        $before = Database::one('SELECT assigned_user_id FROM leads WHERE id = ?', [$id]);
        Database::run('UPDATE leads SET assigned_user_id = ? WHERE id = ?', [$newUserId, $id]);
        $oldName = $before['assigned_user_id'] ? Database::scalar('SELECT name FROM users WHERE id=?', [$before['assigned_user_id']]) : 'Unassigned';
        $newName = Database::scalar('SELECT name FROM users WHERE id=?', [$newUserId]);
        ActivityModel::log('lead', $id, 'reassigned', $note, $oldName, $newName);
        $lead = Database::one('SELECT lead_code, name FROM leads WHERE id=?', [$id]);
        Notifier::send($newUserId, 'lead_assigned', 'Lead assigned: ' . $lead['name'], "Lead {$lead['lead_code']} has been assigned to you.", 'lead', $id);
        AuditLog::record('assign', 'lead', $id, $oldName, $newName);
    }

    public static function addFollowUp(int $id, string $note, ?string $nextDate): void
    {
        ActivityModel::log('lead', $id, 'follow_up', $note);
        if ($nextDate) {
            Database::run('UPDATE leads SET next_followup_date = ? WHERE id = ?', [$nextDate, $id]);
        }
        AuditLog::record('follow_up', 'lead', $id, null, $note);
    }

    public static function softDelete(int $id): void
    {
        Database::run('UPDATE leads SET deleted_at = NOW() WHERE id = ?', [$id]);
        AuditLog::record('delete', 'lead', $id);
    }

    public static function dashboardCounts(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $scope = Permission::scopeIds('leads.view_all', $userId);
        $where = 'deleted_at IS NULL';
        $params = [];
        if ($scope !== null) {
            if (empty($scope)) return ['total' => 0, 'new_today' => 0, 'followups_today' => 0, 'overdue_followups' => 0];
            $ph = implode(',', array_fill(0, count($scope), '?'));
            $where .= " AND (assigned_user_id IN ($ph) OR created_by IN ($ph))";
            $params = array_merge($scope, $scope);
        }
        $total = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE $where", $params);
        $newToday = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE $where AND DATE(created_at) = CURDATE()", $params);
        $followToday = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE $where AND next_followup_date = CURDATE()", $params);
        $overdue = (int)Database::scalar("SELECT COUNT(*) FROM leads WHERE $where AND next_followup_date < CURDATE()", $params);
        return compact('total', 'newToday', 'followToday', 'overdue') + ['total' => $total, 'new_today' => $newToday, 'followups_today' => $followToday, 'overdue_followups' => $overdue];
    }

    public static function distinctSources(): array
    {
        return array_column(Database::all("SELECT DISTINCT source FROM leads WHERE source IS NOT NULL AND source <> '' ORDER BY source"), 'source');
    }
}
