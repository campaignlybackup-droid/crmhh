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
        foreach ($rows as &$row) {
            $row['services'] = self::servicesFor((int)$row['id']);
        }
        return [$rows, $p];
    }

    public static function servicesFor(int $clientId): array
    {
        return Database::all(
            'SELECT cs.*, s.name AS service_name, s.unit_label
             FROM client_services cs JOIN services s ON s.id = cs.service_id
             WHERE cs.client_id = ? AND cs.deleted_at IS NULL ORDER BY s.name',
            [$clientId]
        );
    }

    /** Services + work visible to a specific employee for a client (only what's assigned to them). */
    public static function servicesForEmployee(int $clientId, int $userId): array
    {
        return Database::all(
            "SELECT cs.*, s.name AS service_name, s.unit_label, csa.quantity_assigned, csa.quantity_completed AS my_completed, csa.id AS assignment_id
             FROM client_services cs
             JOIN services s ON s.id = cs.service_id
             JOIN client_service_assignments csa ON csa.client_service_id = cs.id
             WHERE cs.client_id = ? AND csa.user_id = ? AND cs.deleted_at IS NULL
             ORDER BY s.name",
            [$clientId, $userId]
        );
    }

    public static function assignmentsFor(int $clientServiceId): array
    {
        return Database::all(
            'SELECT csa.*, u.name AS user_name FROM client_service_assignments csa
             JOIN users u ON u.id = csa.user_id WHERE csa.client_service_id = ? ORDER BY u.name',
            [$clientServiceId]
        );
    }

    public static function create(array $data): int
    {
        $code = next_code('clients', 'client_code', 'CL');
        Database::run(
            'INSERT INTO clients (client_code, name, company, contact_person, phone, email, website, status, start_date, renewal_date, drive_link, notes, source_lead_id, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $code, $data['name'], $data['company'] ?: null, $data['contact_person'] ?: null, $data['phone'] ?: null,
                $data['email'] ?: null, $data['website'] ?: null, $data['status'] ?: 'active', $data['start_date'] ?: null,
                $data['renewal_date'] ?: null, $data['drive_link'] ?: null, $data['notes'] ?: null, $data['source_lead_id'] ?: null, Auth::id(),
            ]
        );
        $id = (int)Database::lastInsertId();
        ActivityModel::log('client', $id, 'created', 'Client created');
        AuditLog::record('create', 'client', $id, null, $code);
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE clients SET name=?, company=?, contact_person=?, phone=?, email=?, website=?, status=?, start_date=?, renewal_date=?, drive_link=?, notes=? WHERE id=?',
            [
                $data['name'], $data['company'] ?: null, $data['contact_person'] ?: null, $data['phone'] ?: null,
                $data['email'] ?: null, $data['website'] ?: null, $data['status'] ?: 'active', $data['start_date'] ?: null,
                $data['renewal_date'] ?: null, $data['drive_link'] ?: null, $data['notes'] ?: null, $id,
            ]
        );
        ActivityModel::log('client', $id, 'updated', 'Client details updated');
        AuditLog::record('update', 'client', $id);
    }

    public static function softDelete(int $id): void
    {
        Database::run('UPDATE clients SET deleted_at = NOW() WHERE id = ?', [$id]);
        AuditLog::record('delete', 'client', $id);
    }

    public static function addService(int $clientId, int $serviceId, int $quantityRequired, ?int $managerId, ?string $startDate, ?string $endDate, ?string $notes, ?string $scopeDetails = null, ?int $assigneeId = null): int
    {
        Database::run(
            'INSERT INTO client_services (client_id, service_id, quantity_required, manager_id, start_date, end_date, scope_details, notes, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())',
            [$clientId, $serviceId, $quantityRequired, $managerId ?: null, $startDate ?: null, $endDate ?: null, $scopeDetails, $notes ?: null, Auth::id()]
        );
        $id = (int)Database::lastInsertId();
        $serviceName = Database::scalar('SELECT name FROM services WHERE id = ?', [$serviceId]);
        ActivityModel::log('client', $clientId, 'service_added', "$serviceName added: $quantityRequired required");
        if ($managerId) {
            Notifier::send($managerId, 'client_service_assigned', 'New client work assigned', "$serviceName work for this client has been assigned to you.", 'client', $clientId);
        }
        AuditLog::record('add_service', 'client', $clientId, null, $serviceName);

        if ($assigneeId) {
            self::assignEmployeeToService($id, $assigneeId, $quantityRequired);
        }

        return $id;
    }

    public static function updateService(int $clientServiceId, int $quantityRequired, ?int $managerId, ?string $startDate, ?string $endDate, ?string $notes, string $status): void
    {
        Database::run(
            'UPDATE client_services SET quantity_required=?, manager_id=?, start_date=?, end_date=?, notes=?, status=? WHERE id=?',
            [$quantityRequired, $managerId ?: null, $startDate ?: null, $endDate ?: null, $notes ?: null, $status, $clientServiceId]
        );
        $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$clientServiceId]);
        if ($cs) {
            ActivityModel::log('client', (int)$cs['client_id'], 'service_updated', "{$cs['service_name']} requirement updated: $quantityRequired required, status $status");
        }
        AuditLog::record('update_service', 'client_service', $clientServiceId, null, "qty=$quantityRequired,status=$status");
    }

    public static function assignEmployeeToService(int $clientServiceId, int $userId, ?int $quantity): int
    {
        $existing = Database::one('SELECT id FROM client_service_assignments WHERE client_service_id = ? AND user_id = ?', [$clientServiceId, $userId]);
        if ($existing) {
            Database::run('UPDATE client_service_assignments SET quantity_assigned = ? WHERE id = ?', [$quantity, $existing['id']]);
            $id = (int)$existing['id'];
        } else {
            Database::run(
                'INSERT INTO client_service_assignments (client_service_id, user_id, quantity_assigned, assigned_by, assigned_at) VALUES (?,?,?,?,NOW())',
                [$clientServiceId, $userId, $quantity, Auth::id()]
            );
            $id = (int)Database::lastInsertId();
        }
        $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$clientServiceId]);
        if ($cs) {
            ActivityModel::log('client', (int)$cs['client_id'], 'work_assigned', "{$cs['service_name']} work assigned to user");
            Notifier::send($userId, 'work_assigned', 'New work assigned', "{$cs['service_name']} work has been assigned to you.", 'client', (int)$cs['client_id']);
        }
        return $id;
    }

    public static function removeEmployeeFromService(int $assignmentId): void
    {
        Database::run('DELETE FROM client_service_assignments WHERE id = ?', [$assignmentId]);
    }

    /** Employee updates their own progress on an assignment; rolls up into client_services.quantity_completed. */
    public static function updateProgress(int $assignmentId, int $completed, int $userId): void
    {
        $a = Database::one('SELECT * FROM client_service_assignments WHERE id = ? AND user_id = ?', [$assignmentId, $userId]);
        if (!$a) {
            Permission::deny();
        }
        Database::run('UPDATE client_service_assignments SET quantity_completed = ? WHERE id = ?', [$completed, $assignmentId]);
        $sum = (int)Database::scalar('SELECT COALESCE(SUM(quantity_completed),0) FROM client_service_assignments WHERE client_service_id = ?', [$a['client_service_id']]);
        Database::run('UPDATE client_services SET quantity_completed = ? WHERE id = ?', [$sum, $a['client_service_id']]);
        $cs = Database::one('SELECT cs.client_id, s.name AS service_name FROM client_services cs JOIN services s ON s.id = cs.service_id WHERE cs.id = ?', [$a['client_service_id']]);
        if ($cs) {
            ActivityModel::log('client', (int)$cs['client_id'], 'progress_updated', "{$cs['service_name']} progress updated to $completed");
        }
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
            "SELECT cs.*, s.name AS service_name, s.unit_label, csa.quantity_assigned, csa.quantity_completed AS my_completed, csa.id AS assignment_id, c.name AS client_name, c.client_code
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
