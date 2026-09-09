<?php

class ProposalModel
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, u.name AS assigned_name, c.name AS creator_name, cl.name AS client_name, l.company_name AS lead_name
             FROM proposals p
             LEFT JOIN users u ON u.id = p.assigned_user_id
             JOIN users c ON c.id = p.created_by
             LEFT JOIN clients cl ON cl.id = p.client_id
             LEFT JOIN leads l ON l.id = p.lead_id
             WHERE p.id = ?',
            [$id]
        );
    }

    public static function create(array $data): int
    {
        $deadlineHours = (int)($data['deadline_hours'] ?? 24);
        
        Database::run(
            'INSERT INTO proposals (title, business_details, priority, deadline_hours, deadline_at, status, assigned_user_id, created_by, client_id, lead_id, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?, ?, ?, ?, NOW())',
            [
                $data['title'],
                $data['business_details'],
                $data['priority'] ?? 'Medium',
                $deadlineHours,
                $deadlineHours,
                'pending',
                $data['assigned_user_id'] ?: null,
                Auth::id(),
                $data['client_id'] ?: null,
                $data['lead_id'] ?: null
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateStatus(int $id, string $status, ?string $notes = null): void
    {
        Database::run(
            'UPDATE proposals SET status = ?, notes = ? WHERE id = ?',
            [$status, $notes, $id]
        );
    }

    public static function paginate(int $page, int $perPage, array $filters = [], ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $where = ['1=1'];
        $params = [];

        if (Permission::has('proposals.manage', $userId)) {
            // Can see all
        } elseif (Permission::has('proposals.view', $userId)) {
            // Only see assigned
            $where[] = "p.assigned_user_id = ?";
            $params[] = $userId;
        } else {
            // Should not reach here due to controller protection
            $where[] = "1=0";
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM proposals p WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT p.*, u.name AS assigned_name, c.name AS creator_name, cl.name AS client_name, l.company_name AS lead_name 
             FROM proposals p
             LEFT JOIN users u ON u.id = p.assigned_user_id
             JOIN users c ON c.id = p.created_by
             LEFT JOIN clients cl ON cl.id = p.client_id
             LEFT JOIN leads l ON l.id = p.lead_id
             WHERE $whereSql
             ORDER BY p.deadline_at ASC
             LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }
}
