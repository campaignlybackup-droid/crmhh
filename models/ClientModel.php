<?php

class ClientModel
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function paginate(int $page, int $perPage, array $filters, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $vis = Permission::clientVisibility($userId);
        $where = ['clients.deleted_at IS NULL', '(' . $vis['sql'] . ')'];
        $params = $vis['params'];

        if (!empty($filters['status'])) { $where[] = 'clients.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['renewal']) && $filters['renewal'] === 'upcoming') { $where[] = 'clients.renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)'; }
        if (!empty($filters['renewal']) && $filters['renewal'] === 'overdue') { $where[] = 'clients.renewal_date < CURDATE()'; }
        if (!empty($filters['service_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM client_services cs2 WHERE cs2.client_id = clients.id AND cs2.service_id = ? AND cs2.deleted_at IS NULL)';
            $params[] = $filters['service_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(clients.name LIKE ? OR clients.company LIKE ? OR clients.client_code LIKE ? OR clients.contact_person LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM clients WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT clients.* FROM clients WHERE $whereSql ORDER BY clients.name ASC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        $fullAccess = Permission::hasAny(['clients.view_all', 'clients.edit', 'clients.manage_services'], $userId);
        foreach ($rows as &$row) {
            if ($fullAccess) {
                $row['services'] = self::servicesFor((int)$row['id']);
            } else {
                $row['services'] = self::servicesForEmployee((int)$row['id'], $userId);
            }
        }
        return [$rows, $p];
    }

    public static function servicesFor(int $clientId): array
    {
        $services = Database::all(
            'SELECT cs.*, s.name AS service_name, s.unit_label
             FROM client_services cs JOIN services s ON s.id = cs.service_id
             WHERE cs.client_id = ? AND cs.deleted_at IS NULL ORDER BY s.name',
            [$clientId]
        );
        return self::attachSubcategories($services);
    }

    public static function servicesForEmployee(int $clientId, int $userId): array
    {
        $services = Database::all(
            "SELECT cs.*, s.name AS service_name, s.unit_label, csa.quantity_assigned, csa.quantity_completed AS my_completed, csa.id AS assignment_id
             FROM client_services cs
             JOIN services s ON s.id = cs.service_id
             JOIN client_service_assignments csa ON csa.client_service_id = cs.id
             WHERE cs.client_id = ? AND csa.user_id = ? AND cs.deleted_at IS NULL
             ORDER BY s.name",
            [$clientId, $userId]
        );
        return self::attachSubcategories($services);
    }

    private static function attachSubcategories(array $services): array
    {
        if (empty($services)) return [];
        $csIds = array_column($services, 'id');
        $in = str_repeat('?,', count($csIds) - 1) . '?';
        $quantities = Database::all("SELECT csq.*, sub.name as subcategory_name FROM client_service_quantities csq JOIN service_subcategories sub ON sub.id = csq.subcategory_id WHERE csq.client_service_id IN ($in)", $csIds);
        $grouped = [];
        foreach ($quantities as $q) {
            $grouped[$q['client_service_id']][] = $q;
        }
        foreach ($services as &$s) {
            $s['subcategories'] = $grouped[$s['id']] ?? [];
        }
        return $services;
    }

    public static function assignmentsFor(int $clientServiceId): array
    {
        return Database::all(
            'SELECT csa.*, u.name AS user_name FROM client_service_assignments csa
             JOIN users u ON u.id = csa.user_id WHERE csa.client_service_id = ? ORDER BY csa.id ASC',
            [$clientServiceId]
        );
    }

    public static function create(array $data): int
    {
        $code = next_code('clients', 'client_code', 'CL');
        Database::run(
            'INSERT INTO clients (client_code, name, company, contact_person, phone, email, website, status, start_date, renewal_date, retention_date, drive_link, notes, source_lead_id, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $code, $data['name'], $data['company'] ?: null, $data['contact_person'] ?: null, $data['phone'] ?: null,
                $data['email'] ?: null, $data['website'] ?: null, $data['status'] ?: 'active', $data['start_date'] ?: null,
                $data['renewal_date'] ?: null, $data['retention_date'] ?: null, $data['drive_link'] ?: null, $data['notes'] ?: null, $data['source_lead_id'] ?: null, Auth::id(),
            ]
        );
        $id = (int)Database::lastInsertId();
        ActivityModel::log('client', $id, 'created', 'Client created');
        AuditLog::record('create', 'client', $id, null, $code);
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $oldClient = Database::one("SELECT start_date, renewal_date FROM clients WHERE id = ?", [$id]);

        Database::run(
            'UPDATE clients SET name=?, company=?, contact_person=?, phone=?, email=?, website=?, status=?, start_date=?, renewal_date=?, retention_date=?, drive_link=?, notes=? WHERE id=?',
            [
                $data['name'], $data['company'] ?: null, $data['contact_person'] ?: null, $data['phone'] ?: null,
                $data['email'] ?: null, $data['website'] ?: null, $data['status'] ?: 'active', $data['start_date'] ?: null,
                $data['renewal_date'] ?: null, $data['retention_date'] ?: null, $data['drive_link'] ?: null, $data['notes'] ?: null, $id,
            ]
        );

        // Check if deliverables and progress should be reset to 0:
        // 1. Explicitly requested via reset_cycle flag
        // 2. Or the start_date / renewal_date was changed to a different month
        $shouldReset = !empty($data['reset_cycle']);
        if (!$shouldReset && $oldClient) {
            $oldStartMonth = !empty($oldClient['start_date']) ? substr($oldClient['start_date'], 0, 7) : '';
            $newStartMonth = !empty($data['start_date']) ? substr($data['start_date'], 0, 7) : '';
            $oldRenewalMonth = !empty($oldClient['renewal_date']) ? substr($oldClient['renewal_date'], 0, 7) : '';
            $newRenewalMonth = !empty($data['renewal_date']) ? substr($data['renewal_date'], 0, 7) : '';

            if (($newStartMonth && $oldStartMonth && $newStartMonth !== $oldStartMonth) ||
                ($newRenewalMonth && $oldRenewalMonth && $newRenewalMonth !== $oldRenewalMonth)) {
                $shouldReset = true;
            }
        }

        if ($shouldReset) {
            self::resetCycleProgressOnly($id);
        }

        ActivityModel::log('client', $id, 'updated', 'Client details updated' . ($shouldReset ? ' (deliverables & work progress reset to 0 for new cycle)' : ''));
        AuditLog::record('update', 'client', $id, null, $shouldReset ? 'cycle_reset' : null);
    }

    /**
     * Resets completed counts (Reels, Posts, Work Requirements) back to 0 for a client.
     */
    public static function resetCycleProgressOnly(int $clientId): void
    {
        Database::run("UPDATE clients SET reels_completed = 0, posts_completed = 0 WHERE id = ?", [$clientId]);
        Database::run("UPDATE client_services SET quantity_completed = 0 WHERE client_id = ? AND deleted_at IS NULL", [$clientId]);
        Database::run(
            "UPDATE client_service_quantities csq
             JOIN client_services cs ON cs.id = csq.client_service_id
             SET csq.quantity_completed = 0
             WHERE cs.client_id = ? AND cs.deleted_at IS NULL",
            [$clientId]
        );
        Database::run(
            "UPDATE client_service_assignments csa
             JOIN client_services cs ON cs.id = csa.client_service_id
             SET csa.quantity_completed = 0
             WHERE cs.client_id = ? AND cs.deleted_at IS NULL",
            [$clientId]
        );
    }

    /**
     * Complete reset of client cycle with new dates and resetting all progress to 0.
     */
    public static function resetCycle(int $clientId, ?string $newStartDate = null, ?string $newRenewalDate = null, ?string $newRetentionDate = null): void
    {
        $client = Database::one("SELECT * FROM clients WHERE id = ?", [$clientId]);
        if (!$client) return;

        $updates = ["reels_completed = 0", "posts_completed = 0"];
        $params = [];

        if ($newStartDate !== null) {
            $updates[] = "start_date = ?";
            $params[] = $newStartDate ?: null;
        }
        if ($newRenewalDate !== null) {
            $updates[] = "renewal_date = ?";
            $params[] = $newRenewalDate ?: null;
        }
        if ($newRetentionDate !== null) {
            $updates[] = "retention_date = ?";
            $params[] = $newRetentionDate ?: null;
        }

        $params[] = $clientId;
        Database::run("UPDATE clients SET " . implode(', ', $updates) . " WHERE id = ?", $params);

        self::resetCycleProgressOnly($clientId);

        ActivityModel::log('client', $clientId, 'cycle_reset', 'New monthly cycle started. Deliverables & work requirements progress reset to 0.');
        AuditLog::record('cycle_reset', 'client', $clientId, null, 'Monthly cycle reset to 0');
    }

    public static function softDelete(int $id): void
    {
        Database::run('UPDATE clients SET deleted_at = NOW() WHERE id = ?', [$id]);
        AuditLog::record('delete', 'client', $id);
    }

    public static function addService(int $clientId, int $serviceId, int $quantityRequired, ?int $managerId, ?string $startDate, ?string $endDate, ?string $notes, ?string $scopeDetails = null, ?int $assigneeId = null, string $tenure = 'monthly', array $subcategories = []): int
    {
        Database::run(
            'INSERT INTO client_services (client_id, service_id, quantity_required, manager_id, start_date, end_date, tenure, scope_details, notes, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
            [$clientId, $serviceId, $quantityRequired, $managerId ?: null, $startDate ?: null, $endDate ?: null, $tenure, $scopeDetails, $notes ?: null, Auth::id()]
        );
        $id = (int)Database::lastInsertId();
        
        foreach ($subcategories as $subId => $qty) {
            if ((int)$qty > 0) {
                Database::run('INSERT INTO client_service_quantities (client_service_id, subcategory_id, quantity_required) VALUES (?,?,?)', [$id, (int)$subId, (int)$qty]);
            }
        }
        
        $serviceName = Database::scalar('SELECT name FROM services WHERE id = ?', [$serviceId]);
        ActivityModel::log('client', $clientId, 'service_added', "$serviceName added: $quantityRequired required");
        if ($managerId) {
            Notifier::send($managerId, 'client_service_assigned', 'New client work assigned', "$serviceName work for this client has been assigned to you.", 'client', $clientId);
        }
        AuditLog::record('add_service', 'client', $clientId, null, $serviceName);

        if ($assigneeId) {
            self::addRequirement($id, 'Initial Assignment', $assigneeId, $quantityRequired, null, null);
        }

        return $id;
    }

    public static function updateService(int $clientServiceId, int $quantityRequired, ?int $managerId, ?string $startDate, ?string $endDate, ?string $notes, string $status, string $tenure = 'monthly', array $subcategories = []): void
    {
        Database::run(
            'UPDATE client_services SET quantity_required=?, manager_id=?, start_date=?, end_date=?, tenure=?, notes=?, status=? WHERE id=?',
            [$quantityRequired, $managerId ?: null, $startDate ?: null, $endDate ?: null, $tenure, $notes ?: null, $status, $clientServiceId]
        );
        
        Database::run('DELETE FROM client_service_quantities WHERE client_service_id = ?', [$clientServiceId]);
        foreach ($subcategories as $subId => $qty) {
            if ((int)$qty > 0) {
                Database::run('INSERT INTO client_service_quantities (client_service_id, subcategory_id, quantity_required) VALUES (?,?,?)', [$clientServiceId, (int)$subId, (int)$qty]);
            }
        }
        $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$clientServiceId]);
        if ($cs) {
            ActivityModel::log('client', (int)$cs['client_id'], 'service_updated', "{$cs['service_name']} requirement updated: $quantityRequired required, status $status");
        }
        AuditLog::record('update_service', 'client_service', $clientServiceId, null, "qty=$quantityRequired,status=$status");
    }

    public static function removeService(int $clientServiceId): void
    {
        $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$clientServiceId]);
        Database::run('UPDATE client_services SET deleted_at = NOW() WHERE id = ?', [$clientServiceId]);
        if ($cs) {
            ActivityModel::log('client', (int)$cs['client_id'], 'service_removed', "{$cs['service_name']} service was removed from the client.");
            AuditLog::record('remove_service', 'client_service', $clientServiceId, null, "Removed {$cs['service_name']}");
        }
    }

    public static function addRequirement(int $clientServiceId, string $reqName, int $userId, ?int $quantity, ?string $deadline, ?string $notes): int
    {
        Database::run(
            'INSERT INTO client_service_assignments (client_service_id, requirement_name, user_id, quantity_assigned, deadline, notes, assigned_by, assigned_at) VALUES (?,?,?,?,?,?,?,NOW())',
            [$clientServiceId, $reqName, $userId, $quantity, $deadline ?: null, $notes ?: null, Auth::id()]
        );
        $id = (int)Database::lastInsertId();
        
        $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$clientServiceId]);
        if ($cs) {
            ActivityModel::log('client', (int)$cs['client_id'], 'work_assigned', "$reqName assigned to user for {$cs['service_name']}");
            Notifier::send($userId, 'work_assigned', 'New requirement assigned', "You have been assigned to: $reqName", 'client', (int)$cs['client_id']);
        }
        return $id;
    }

    public static function removeEmployeeFromService(int $assignmentId): void
    {
        $a = Database::one('SELECT * FROM client_service_assignments WHERE id = ?', [$assignmentId]);
        if ($a) {
            $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$a['client_service_id']]);
            Database::run('DELETE FROM client_service_assignments WHERE id = ?', [$assignmentId]);
            if ($cs) {
                ActivityModel::log('client', (int)$cs['client_id'], 'work_unassigned', "{$a['requirement_name']} assignment was removed.");
                AuditLog::record('remove_assignment', 'client_service_assignment', $assignmentId, null, "Removed assignment {$a['requirement_name']}");
            }
        }
    }

    /** Update progress on an assignment; rolls up into client_services.quantity_completed.
     * Allowed for: the assigned employee, the service manager, or managers/founders with service management permissions.
     */
    public static function updateProgress(int $assignmentId, int $completed, int $userId): void
    {
        $a = Database::one(
            'SELECT csa.*, cs.client_id, cs.manager_id, s.name AS service_name
             FROM client_service_assignments csa
             JOIN client_services cs ON cs.id = csa.client_service_id
             JOIN services s ON s.id = cs.service_id
             WHERE csa.id = ?',
            [$assignmentId]
        );
        if (!$a) {
            Permission::deny();
        }

        $canUpdate = ((int)$a['user_id'] === $userId)
            || ((int)$a['manager_id'] === $userId)
            || Permission::hasAny(['clients.manage_services', 'clients.edit', 'clients.assign'], $userId);

        if (!$canUpdate) {
            Permission::deny();
        }

        $completed = max(0, $completed);
        Database::run('UPDATE client_service_assignments SET quantity_completed = ? WHERE id = ?', [$completed, $assignmentId]);
        $sum = (int)Database::scalar('SELECT COALESCE(SUM(quantity_completed),0) FROM client_service_assignments WHERE client_service_id = ?', [$a['client_service_id']]);
        Database::run('UPDATE client_services SET quantity_completed = ? WHERE id = ?', [$sum, $a['client_service_id']]);
        ActivityModel::log('client', (int)$a['client_id'], 'progress_updated', "{$a['service_name']} ({$a['requirement_name']}) progress updated to $completed");
    }

    /** Direct update of Reels and Posts completed / required quantities for a client */
    public static function updateDeliverableCompleted(int $clientId, string $type, int $completed, ?int $required = null, ?int $userId = null): void
    {
        $userId = $userId ?? Auth::id();
        $completed = max(0, $completed);
        
        $type = in_array(strtolower($type), ['reel', 'reels', 'video'], true) ? 'reels' : 'posts';
        $colComp = "{$type}_completed";
        $colReq = "{$type}_required";

        if ($required !== null) {
            $required = max(0, $required);
            Database::run("UPDATE clients SET $colComp = ?, $colReq = ? WHERE id = ?", [$completed, $required, $clientId]);
        } else {
            Database::run("UPDATE clients SET $colComp = ? WHERE id = ?", [$completed, $clientId]);
        }

        // Also sync with matching client_service_quantities and client_services if configured
        try {
            $subLike = ($type === 'reels') ? '%reel%' : '%post%';
            $matchingSubIds = Database::all(
                "SELECT csq.id, csq.client_service_id 
                 FROM client_service_quantities csq
                 JOIN client_services cs ON cs.id = csq.client_service_id
                 JOIN service_subcategories sub ON sub.id = csq.subcategory_id
                 WHERE cs.client_id = ? AND cs.deleted_at IS NULL AND (sub.name LIKE ? OR sub.name LIKE ?)",
                [$clientId, $subLike, ($type === 'reels' ? '%video%' : '%static%')]
            );

            foreach ($matchingSubIds as $m) {
                Database::run("UPDATE client_service_quantities SET quantity_completed = ? WHERE id = ?", [$completed, $m['id']]);
                $sum = (int)Database::scalar("SELECT COALESCE(SUM(quantity_completed),0) FROM client_service_quantities WHERE client_service_id = ?", [$m['client_service_id']]);
                Database::run("UPDATE client_services SET quantity_completed = ? WHERE id = ?", [$sum, $m['client_service_id']]);
            }
        } catch (Throwable $e) {}

        ActivityModel::log('client', $clientId, 'progress_updated', ucfirst($type) . " completed quantity updated to $completed");
        AuditLog::record('update_deliverable_completed', 'client', $clientId, null, "Updated $type completed to $completed");
    }

    public static function upcomingRenewals(int $days = 30, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $vis = Permission::clientVisibility($userId);
        return Database::all(
            "SELECT * FROM clients WHERE deleted_at IS NULL AND renewal_date IS NOT NULL
             AND renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY) AND ({$vis['sql']})
             ORDER BY renewal_date ASC LIMIT 10",
            $vis['params']
        );
    }

    public static function myAssignedServices(int $userId): array
    {
        return Database::all(
            "SELECT cs.*, s.name AS service_name, s.unit_label, csa.requirement_name, csa.deadline, csa.notes AS req_notes, csa.quantity_assigned, csa.quantity_completed AS my_completed, csa.id AS assignment_id, c.name AS client_name, c.client_code
             FROM client_services cs
             JOIN services s ON s.id = cs.service_id
             JOIN client_service_assignments csa ON csa.client_service_id = cs.id
             JOIN clients c ON c.id = cs.client_id
             WHERE csa.user_id = ? AND cs.status = 'active' AND cs.deleted_at IS NULL AND c.deleted_at IS NULL
             ORDER BY c.name ASC, s.name ASC",
            [$userId]
        );
    }
}
