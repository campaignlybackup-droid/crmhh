<?php

class LeadModel
{
    public const DEFAULT_STATUS_PALETTE = [
        'new-leads' => '#3b82f6',
        'new' => '#3b82f6',
        'cold-lead' => '#64748b',
        'warm-lead' => '#eab308',
        'contacted' => '#06b6d4',
        'in-discussion' => '#06b6d4',
        'call-scheduled' => '#0d9488',
        'follow-up' => '#f97316',
        'meeting-scheduled' => '#8b5cf6',
        'meet' => '#8b5cf6',
        'long-nurture' => '#14b8a6',
        'almost-closed' => '#0284c7',
        'closed' => '#10b981',
        'won' => '#10b981',
        'dead' => '#ef4444',
        'lost' => '#ef4444',
    ];

    public static function getDefaultStatusColor(?string $statusNameOrSlug, ?string $currentColor = null): string
    {
        if (!empty($currentColor) && $currentColor !== '#6c757d' && $currentColor !== '#ffffff' && $currentColor !== '#000000') {
            return $currentColor;
        }
        if (!$statusNameOrSlug) return '#64748b';
        $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $statusNameOrSlug), '-'));
        
        foreach (self::DEFAULT_STATUS_PALETTE as $key => $color) {
            if ($normalized === $key || str_contains($normalized, $key)) {
                return $color;
            }
        }
        return '#64748b';
    }

    public static function statuses(?int $folderId = null): array
    {
        $rows = [];
        if ($folderId !== null && $folderId > 0) {
            $rows = Database::all('SELECT * FROM lead_statuses WHERE folder_id = ? ORDER BY sort_order ASC', [$folderId]);
        }
        if (empty($rows)) {
            $rows = Database::all('SELECT * FROM lead_statuses WHERE folder_id IS NULL ORDER BY sort_order ASC');
        }
        foreach ($rows as &$r) {
            $r['color'] = self::getDefaultStatusColor($r['slug'] ?? $r['name'], $r['color'] ?? null);
        }
        unset($r);
        return $rows;
    }

    public static function defaultStatusId(?int $folderId = null): ?int
    {
        if ($folderId !== null && $folderId > 0) {
            $row = Database::one("SELECT id FROM lead_statuses WHERE is_default = 1 AND folder_id = ? LIMIT 1", [$folderId]);
            if ($row) return (int)$row['id'];
            $row = Database::one("SELECT id FROM lead_statuses WHERE folder_id = ? ORDER BY sort_order ASC LIMIT 1", [$folderId]);
            if ($row) return (int)$row['id'];
        }
        $row = Database::one("SELECT id FROM lead_statuses WHERE is_default = 1 AND folder_id IS NULL LIMIT 1");
        if ($row) return (int)$row['id'];
        $row = Database::one("SELECT id FROM lead_statuses WHERE folder_id IS NULL ORDER BY sort_order ASC LIMIT 1");
        return $row ? (int)$row['id'] : null;
    }

    public static function getCustomFields(?int $folderId): array
    {
        if (!$folderId) return [];
        return Database::all('SELECT * FROM folder_custom_fields WHERE folder_id = ? ORDER BY sort_order ASC', [$folderId]);
    }

    public static function getCustomValues(int $leadId): array
    {
        $rows = Database::all('SELECT field_id, field_value FROM lead_custom_values WHERE lead_id = ?', [$leadId]);
        $values = [];
        foreach ($rows as $r) {
            $values[$r['field_id']] = $r['field_value'];
        }
        return $values;
    }

    public static function saveCustomValues(int $leadId, array $values)
    {
        foreach ($values as $fieldId => $val) {
            if ($val === '' || $val === null) {
                Database::run('DELETE FROM lead_custom_values WHERE lead_id = ? AND field_id = ?', [$leadId, (int)$fieldId]);
            } else {
                Database::run(
                    'INSERT INTO lead_custom_values (lead_id, field_id, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = ?',
                    [$leadId, (int)$fieldId, $val, $val]
                );
            }
        }
    }

    public static function find(int $id): ?array
    {
        $lead = Database::one(
            'SELECT l.*, ls.name AS status_name, ls.slug AS status_slug, ls.color AS status_color,
                    u.name AS assigned_name, c.name AS created_by_name
             FROM leads l
             JOIN lead_statuses ls ON ls.id = l.status_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             LEFT JOIN users c ON c.id = l.created_by
             WHERE l.id = ? AND l.deleted_at IS NULL',
            [$id]
        );
        if ($lead) {
            $lead['status_color'] = self::getDefaultStatusColor($lead['status_slug'] ?? $lead['status_name'], $lead['status_color'] ?? null);
        }
        return $lead;
    }

    public static function canAccess(int $leadId, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;
        if (Permission::isFounder($userId)) return true;
        
        $lead = Database::one('SELECT assigned_user_id, created_by, folder_id FROM leads WHERE id = ? AND deleted_at IS NULL', [$leadId]);
        if (!$lead) return false;

        $isManager = Permission::isManager($userId);
        $assignedId = (int)($lead['assigned_user_id'] ?? 0);
        $createdById = (int)($lead['created_by'] ?? 0);

        // 1. Direct assignment or creator
        if ($assignedId === $userId || $createdById === $userId) {
            return true;
        }

        // 2. Manager managing assigned user or creator
        if ($isManager) {
            if ($assignedId && Permission::manages($userId, $assignedId)) return true;
            if ($createdById && Permission::manages($userId, $createdById)) return true;
        }

        // 3. Folder access: if the lead belongs to a folder
        if (!empty($lead['folder_id'])) {
            $folderId = (int)$lead['folder_id'];
            $isPublicFolder = ((int)Database::scalar('SELECT COUNT(*) FROM lead_folder_users WHERE folder_id = ?', [$folderId])) === 0;
            $hasFolderAccess = (int)Database::scalar('SELECT COUNT(*) FROM lead_folder_users WHERE folder_id = ? AND user_id = ?', [$folderId, $userId]) > 0;
            
            if ($isPublicFolder || $hasFolderAccess) {
                return true;
            }
        }

        // 4. Unassigned leads in the general pool (no folder)
        if (!$assignedId && empty($lead['folder_id'])) {
            return true;
        }
        
        return false;
    }

    public static function getCustomFolders(int $userId): array
    {
        if (Permission::isFounder($userId)) {
            return Database::all('SELECT lf.*, (SELECT COUNT(*) FROM leads l WHERE l.folder_id = lf.id AND l.deleted_at IS NULL) as lead_count FROM lead_folders lf ORDER BY lf.name');
        }

        $managedIds = Permission::managedUserIds($userId);
        $ph = implode(',', array_fill(0, count($managedIds), '?'));
        
        return Database::all(
            "SELECT DISTINCT lf.*, (SELECT COUNT(*) FROM leads l WHERE l.folder_id = lf.id AND l.deleted_at IS NULL) as lead_count 
             FROM lead_folders lf 
             LEFT JOIN lead_folder_users lfu ON lfu.folder_id = lf.id 
             WHERE lfu.user_id IN ($ph) 
                OR lf.created_by = ? 
                OR (SELECT COUNT(*) FROM lead_folder_users WHERE folder_id = lf.id) = 0
             ORDER BY lf.name",
            array_merge($managedIds, [$userId])
        );
    }

    public static function getFolderStats(int $userId): array
    {
        $isFounder = Permission::isFounder($userId);
        $isManager = Permission::isManager($userId);
        if (!$isFounder && !$isManager) return [];
        
        $sql = "SELECT u.id, u.name, COUNT(l.id) AS lead_count 
                FROM users u 
                JOIN user_roles ur ON ur.user_id = u.id
                JOIN roles r ON r.id = ur.role_id
                LEFT JOIN leads l ON l.assigned_user_id = u.id AND l.deleted_at IS NULL
                WHERE u.status = 'active' AND u.deleted_at IS NULL
                  AND r.slug IN ('manager', 'sales') ";
        $params = [];
                
        if (!$isFounder && $isManager) {
            $managedIds = Permission::managedUserIds($userId);
            $ph = implode(',', array_fill(0, count($managedIds), '?'));
            $sql .= " AND u.id IN ($ph) ";
            $params = $managedIds;
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.name";
        return Database::all($sql, $params);
    }

    public static function getDashboardStats(array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $isFounder = Permission::isFounder($userId);
        $isManager = Permission::isManager($userId);
        $canViewAll = $isFounder;
        
        $where = ['l.deleted_at IS NULL'];
        $params = [];
        
        $assignedUserId = $filters['assigned_user_id'] ?? '';
        $folderId = $filters['folder_id'] ?? '';
        
        if ($folderId !== '') {
            $where[] = "l.folder_id = ?";
            $params[] = $folderId;
            if (!$isFounder && !$canViewAll) {
                $managedIds = Permission::managedUserIds($userId);
                $ph = implode(',', array_fill(0, count($managedIds), '?'));
                $where[] = "(EXISTS (SELECT 1 FROM lead_folder_users lfu WHERE lfu.folder_id = l.folder_id AND lfu.user_id IN ($ph)) OR NOT EXISTS (SELECT 1 FROM lead_folder_users lfu2 WHERE lfu2.folder_id = l.folder_id))";
                $params = array_merge($params, $managedIds);
            }
        } else {
            $where[] = "l.folder_id IS NULL";
            if ($assignedUserId === 'all') {
                if ($isFounder || $canViewAll) {
                    // All leads
                } elseif ($isManager) {
                    $managedIds = Permission::managedUserIds($userId);
                    $ph = implode(',', array_fill(0, count($managedIds), '?'));
                    $where[] = "l.assigned_user_id IN ($ph)";
                    $params = array_merge($params, $managedIds);
                } else {
                    $where[] = "l.assigned_user_id = ?";
                    $params[] = $userId;
                }
            } elseif ($assignedUserId !== '') {
                $targetId = (int)$assignedUserId;
                if ($isFounder || $canViewAll || ($isManager && Permission::manages($userId, $targetId)) || $targetId === $userId) {
                    $where[] = "l.assigned_user_id = ?";
                    $params[] = $targetId;
                } else {
                    $where[] = "l.assigned_user_id = ?";
                    $params[] = $userId;
                }
            } elseif (!$isFounder && !$canViewAll && !$isManager) {
                $where[] = 'l.assigned_user_id = ?';
                $params[] = $userId;
            } elseif ($isManager && !$isFounder && !$canViewAll) {
                $managedIds = Permission::managedUserIds($userId);
                $ph = implode(',', array_fill(0, count($managedIds), '?'));
                $where[] = "l.assigned_user_id IN ($ph)";
                $params = array_merge($params, $managedIds);
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
        $contacted = (int)Database::scalar("SELECT COUNT(*) FROM leads l JOIN lead_statuses ls ON ls.id = l.status_id WHERE $baseWhere $dateWhere AND ls.slug IN ('call-scheduled', 'follow-up', 'meet', 'contacted') AND DATE(l.updated_at) = CURDATE()", $mergedParams);
        $followups = (int)Database::scalar("SELECT COUNT(*) FROM leads l WHERE $baseWhere $dateWhere AND l.next_followup_date = CURDATE()", $mergedParams);
        $pending = (int)Database::scalar("SELECT COUNT(*) FROM leads l JOIN lead_statuses ls ON ls.id = l.status_id WHERE $baseWhere $dateWhere AND ls.slug IN ('new-leads', 'new')", $mergedParams);
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
        
        $isFounder = Permission::isFounder($userId);
        $isManager = Permission::isManager($userId);
        $canViewAll = $isFounder;
        
        $assignedUserId = $filters['assigned_user_id'] ?? '';
        $folderId = $filters['folder_id'] ?? '';
        
        if ($folderId === 'all') {
            if (!$isFounder && !$canViewAll) {
                $managedIds = Permission::managedUserIds($userId);
                $ph = implode(',', array_fill(0, count($managedIds), '?'));
                $where[] = "(l.folder_id IS NULL OR EXISTS (SELECT 1 FROM lead_folder_users lfu WHERE lfu.folder_id = l.folder_id AND lfu.user_id IN ($ph)) OR NOT EXISTS (SELECT 1 FROM lead_folder_users lfu2 WHERE lfu2.folder_id = l.folder_id))";
                $params = array_merge($params, $managedIds);
            }
        } elseif ($folderId !== '') {
            $where[] = "l.folder_id = ?";
            $params[] = $folderId;
            if (!$isFounder && !$canViewAll) {
                $managedIds = Permission::managedUserIds($userId);
                $ph = implode(',', array_fill(0, count($managedIds), '?'));
                $where[] = "(EXISTS (SELECT 1 FROM lead_folder_users lfu WHERE lfu.folder_id = l.folder_id AND lfu.user_id IN ($ph)) OR NOT EXISTS (SELECT 1 FROM lead_folder_users lfu2 WHERE lfu2.folder_id = l.folder_id))";
                $params = array_merge($params, $managedIds);
            }
        } else {
            $where[] = "l.folder_id IS NULL";
            if ($assignedUserId === 'all') {
                if ($isFounder || $canViewAll) {
                    // All
                } elseif ($isManager) {
                    $managedIds = Permission::managedUserIds($userId);
                    $ph = implode(',', array_fill(0, count($managedIds), '?'));
                    $where[] = "l.assigned_user_id IN ($ph)";
                    $params = array_merge($params, $managedIds);
                } else {
                    $where[] = "l.assigned_user_id = ?";
                    $params[] = $userId;
                }
            } elseif ($assignedUserId !== '') {
                $targetId = (int)$assignedUserId;
                if ($isFounder || $canViewAll || ($isManager && Permission::manages($userId, $targetId)) || $targetId === $userId) {
                    $where[] = 'l.assigned_user_id = ?'; 
                    $params[] = $targetId; 
                } else {
                    $where[] = 'l.assigned_user_id = ?';
                    $params[] = $userId;
                }
            } elseif (!$isFounder && !$canViewAll && !$isManager) {
                $where[] = 'l.assigned_user_id = ?';
                $params[] = $userId;
            } elseif ($isManager && !$isFounder && !$canViewAll) {
                $managedIds = Permission::managedUserIds($userId);
                $ph = implode(',', array_fill(0, count($managedIds), '?'));
                $where[] = "l.assigned_user_id IN ($ph)";
                $params = array_merge($params, $managedIds);
            }
        }

        if (!empty($filters['status_id'])) { 
            $where[] = 'l.status_id = ?'; 
            $params[] = $filters['status_id']; 
        } elseif (!empty($filters['hide_dead'])) {
            $where[] = "(ls.slug NOT IN ('dead', 'lost') AND LOWER(ls.name) NOT LIKE '%dead%' AND ls.is_lost = 0)";
        }
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
        $total = (int)Database::scalar("SELECT COUNT(*) FROM leads l LEFT JOIN lead_statuses ls ON ls.id = l.status_id WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT l.*, ls.name AS status_name, ls.slug AS status_slug, ls.color AS status_color, u.name AS assigned_name
             FROM leads l
             JOIN lead_statuses ls ON ls.id = l.status_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             WHERE $whereSql 
             ORDER BY 
                (CASE WHEN LOWER(ls.slug) IN ('dead', 'lost') OR LOWER(ls.name) LIKE '%dead%' OR ls.is_lost = 1 THEN 1 ELSE 0 END) ASC,
                l.created_at DESC 
             LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        foreach ($rows as &$r) {
            $r['status_color'] = self::getDefaultStatusColor($r['status_slug'] ?? $r['status_name'], $r['status_color'] ?? null);
        }
        unset($r);
        return [$rows, $p];
    }

    public static function findByPhoneOrEmail(?string $phone, ?string $email, ?int $folderId = null): ?array
    {
        $folderSql = $folderId === null ? "folder_id IS NULL" : "folder_id = " . (int)$folderId;
        $normPhone = normalize_phone($phone);
        if ($normPhone !== '') {
            $row = Database::one(
                "SELECT * FROM leads WHERE deleted_at IS NULL AND $folderSql AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), 10) = ? LIMIT 1",
                [$normPhone]
            );
            if ($row) return $row;
        }
        if (valid_email($email)) {
            $row = Database::one("SELECT * FROM leads WHERE deleted_at IS NULL AND $folderSql AND LOWER(email) = ? LIMIT 1", [strtolower(trim($email))]);
            if ($row) return $row;
        }
        return null;
    }

    public static function create(array $data): int
    {
        $code = next_code('leads', 'lead_code', 'LD');
        Database::run(
            'INSERT INTO leads (lead_code, name, phone, email, company, source, status_id, assigned_user_id, created_by, next_followup_date, next_step, notes, folder_id, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $code, $data['name'], $data['phone'] ?: null, $data['email'] ?: null, $data['company'] ?: null,
                $data['source'] ?: null, $data['status_id'] ?: self::defaultStatusId(), $data['assigned_user_id'] ?: null,
                $data['created_by'] ?? Auth::id(), $data['next_followup_date'] ?: null, $data['next_step'] ?: null, $data['notes'] ?: null,
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
            'UPDATE leads SET name=?, phone=?, email=?, company=?, source=?, notes=?, next_followup_date=?, next_step=? WHERE id=?',
            [$data['name'], $data['phone'] ?: null, $data['email'] ?: null, $data['company'] ?: null, $data['source'] ?: null, $data['notes'] ?: null, $data['next_followup_date'] ?: null, $data['next_step'] ?: null, $id]
        );

        if (!empty($data['status_id']) && (int)$data['status_id'] !== (int)$before['status_id']) {
            self::changeStatus($id, (int)$data['status_id']);
        }
        if (array_key_exists('assigned_user_id', $data)) {
            $newAssignee = $data['assigned_user_id'] ? (int)$data['assigned_user_id'] : null;
            if ($newAssignee !== (int)$before['assigned_user_id']) {
                self::assign($id, $newAssignee);
            }
        }
        if (array_key_exists('folder_id', $data)) {
            $newFolder = $data['folder_id'] ? (int)$data['folder_id'] : null;
            if ($newFolder !== $before['folder_id']) {
                Database::run('UPDATE leads SET folder_id = ? WHERE id = ?', [$newFolder, $id]);
            }
        }
        AuditLog::record('update', 'lead', $id);
    }

    public static function updateField(int $id, string $field, $newValue, int $userId): array
    {
        $allowedFields = ['name', 'phone', 'email', 'company', 'source', 'status_id', 'assigned_user_id', 'next_followup_date', 'next_step', 'notes', 'folder_id', 'docs_link', 'docs_access'];
        
        Database::beginTransaction();
        try {
            $before = self::find($id);
            if (!$before) throw new \Exception("Lead not found");

            if (strpos($field, 'custom_field_') === 0) {
                $cfId = (int)str_replace('custom_field_', '', $field);
                $oldValue = Database::scalar('SELECT field_value FROM lead_custom_values WHERE lead_id = ? AND field_id = ?', [$id, $cfId]);
                
                if ((string)$oldValue === (string)$newValue) {
                    Database::commit();
                    return ['success' => true, 'old_value' => $oldValue, 'new_value' => $newValue];
                }

                if ($newValue === '' || $newValue === null) {
                    Database::run('DELETE FROM lead_custom_values WHERE lead_id = ? AND field_id = ?', [$id, $cfId]);
                } else {
                    Database::run(
                        'INSERT INTO lead_custom_values (lead_id, field_id, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = ?',
                        [$id, $cfId, $newValue, $newValue]
                    );
                }
                
                $cfName = Database::scalar('SELECT field_name FROM folder_custom_fields WHERE id = ?', [$cfId]) ?: 'Custom Field';
                ActivityModel::log('lead', $id, 'updated', "Changed $cfName", (string)$oldValue, (string)$newValue);
                
                Database::run('UPDATE leads SET updated_at = NOW() WHERE id = ?', [$id]);
                Database::commit();
                return ['success' => true, 'old_value' => $oldValue, 'new_value' => $newValue];
            }

            if (!in_array($field, $allowedFields, true)) {
                throw new \Exception("Invalid field");
            }

            $oldValue = $before[$field];

            if ((string)$oldValue === (string)$newValue) {
                Database::commit();
                return ['success' => true, 'old_value' => $oldValue, 'new_value' => $newValue];
            }

            if ($field === 'status_id') {
                $oldName = Database::scalar('SELECT name FROM lead_statuses WHERE id = ?', [(int)$oldValue]);
                $statusRow = Database::one('SELECT name, slug, color FROM lead_statuses WHERE id = ?', [(int)$newValue]);
                $newName = $statusRow['name'] ?? 'Unknown';
                $statusColor = self::getDefaultStatusColor($statusRow['slug'] ?? $newName, $statusRow['color'] ?? null);
                Database::run('UPDATE leads SET status_id = ? WHERE id = ?', [$newValue, $id]);
                ActivityModel::log('lead', $id, 'status_changed', "Changed Status", (string)$oldName, (string)$newName);
                Database::run('UPDATE leads SET updated_at = NOW() WHERE id = ?', [$id]);
                Database::commit();
                return ['success' => true, 'old_value' => $oldValue, 'new_value' => $newValue, 'status_color' => $statusColor, 'status_name' => $newName];
            } elseif ($field === 'assigned_user_id') {
                $oldName = $oldValue ? Database::scalar('SELECT name FROM users WHERE id=?', [$oldValue]) : 'Unassigned';
                $newName = $newValue ? Database::scalar('SELECT name FROM users WHERE id=?', [$newValue]) : 'Unassigned';
                Database::run('UPDATE leads SET assigned_user_id = ? WHERE id = ?', [$newValue ?: null, $id]);
                ActivityModel::log('lead', $id, 'reassigned', "Reassigned Lead", (string)$oldName, (string)$newName);
                if ($newValue) {
                    $lead = Database::one('SELECT lead_code, name FROM leads WHERE id=?', [$id]);
                    $isFounder = (int)Database::scalar('SELECT COUNT(*) FROM users WHERE id = ? AND is_founder = 1', [(int)$newValue]) > 0;
                    if ($isFounder && !Permission::isFounder() && !Permission::isManager()) {
                        ActivityModel::log('lead', $id, 'escalated', "Lead escalated to Founder ($newName) by " . (Auth::user()['name'] ?? 'User'));
                        Notifier::send((int)$newValue, 'lead_escalated', '🚨 Lead Escalation: ' . $lead['name'], "Lead {$lead['lead_code']} ({$lead['name']}) has been escalated to you by " . (Auth::user()['name'] ?? 'User') . ".", 'lead', $id);
                    } else {
                        Notifier::send((int)$newValue, 'lead_assigned', 'Lead assigned: ' . $lead['name'], "Lead {$lead['lead_code']} has been assigned to you.", 'lead', $id);
                    }
                }
            } elseif ($field === 'folder_id') {
                $oldName = $oldValue ? Database::scalar('SELECT name FROM lead_folders WHERE id=?', [$oldValue]) : 'Main';
                $newName = $newValue ? Database::scalar('SELECT name FROM lead_folders WHERE id=?', [$newValue]) : 'Main';
                Database::run('UPDATE leads SET folder_id = ? WHERE id = ?', [$newValue ?: null, $id]);
                ActivityModel::log('lead', $id, 'updated', "Changed Folder", (string)$oldName, (string)$newName);
            } else {
                Database::run("UPDATE leads SET $field = ? WHERE id = ?", [$newValue !== '' ? $newValue : null, $id]);
                $humanField = ucwords(str_replace('_', ' ', $field));
                ActivityModel::log('lead', $id, 'updated', "Changed $humanField", (string)$oldValue, (string)$newValue);
            }

            Database::run('UPDATE leads SET updated_at = NOW() WHERE id = ?', [$id]);
            Database::commit();
            return ['success' => true, 'old_value' => $oldValue, 'new_value' => $newValue];

        } catch (\Exception $e) {
            Database::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
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

    public static function assign(int $id, ?int $newUserId, ?string $note = null): void
    {
        $before = Database::one('SELECT assigned_user_id FROM leads WHERE id = ?', [$id]);
        Database::run('UPDATE leads SET assigned_user_id = ? WHERE id = ?', [$newUserId, $id]);
        $oldName = $before['assigned_user_id'] ? Database::scalar('SELECT name FROM users WHERE id=?', [$before['assigned_user_id']]) : 'Unassigned';
        $newName = $newUserId ? Database::scalar('SELECT name FROM users WHERE id=?', [$newUserId]) : 'Unassigned';
        ActivityModel::log('lead', $id, 'reassigned', $note, $oldName, $newName);
        if ($newUserId) {
            $lead = Database::one('SELECT lead_code, name FROM leads WHERE id=?', [$id]);
            Notifier::send($newUserId, 'lead_assigned', 'Lead assigned: ' . $lead['name'], "Lead {$lead['lead_code']} has been assigned to you.", 'lead', $id);
        }
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

    public static function updateStatusColor(int $statusId, string $color): void
    {
        Database::run('UPDATE lead_statuses SET color = ? WHERE id = ?', [$color, $statusId]);
    }

    public static function applyDefaultPaletteAcrossAllSheets(?array $palette = null): array
    {
        $statuses = Database::all('SELECT id, name, slug, color, folder_id FROM lead_statuses');
        $updated = 0;
        foreach ($statuses as $st) {
            $newColor = self::getDefaultStatusColor($st['slug'] ?? $st['name']);
            if ($newColor && $newColor !== ($st['color'] ?? '')) {
                Database::run('UPDATE lead_statuses SET color = ? WHERE id = ?', [$newColor, $st['id']]);
                $updated++;
            }
        }
        return ['success' => true, 'updated_count' => $updated];
    }
}
