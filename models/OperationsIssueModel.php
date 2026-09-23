<?php

class OperationsIssueModel
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT oi.*, 
                    u_noticed.name AS noticed_by_name, 
                    u_resp.name AS responsible_name, 
                    u_corr.name AS corrected_by_name,
                    c.name AS client_name,
                    t.title AS task_title
             FROM operations_issues oi
             JOIN users u_noticed ON u_noticed.id = oi.noticed_by_id
             JOIN users u_resp ON u_resp.id = oi.responsible_id
             LEFT JOIN users u_corr ON u_corr.id = oi.corrected_by_id
             LEFT JOIN clients c ON c.id = oi.client_id
             LEFT JOIN tasks t ON t.id = oi.task_id
             WHERE oi.id = ?',
            [$id]
        );
    }

    public static function create(array $data): int
    {
        $db = Database::pdo();
        $stmt = $db->prepare('INSERT INTO operations_issues 
            (issue_title, description, noticed_by_id, responsible_id, deduction_amount, deduction_type, client_id, task_id, content_id, severity, status, due_date, notified_founder, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
        
        $stmt->execute([
            $data['issue_title'],
            $data['description'] ?: null,
            $data['noticed_by_id'],
            $data['responsible_id'],
            $data['deduction_amount'] ?? 0.00,
            $data['deduction_type'] ?? 'deduction',
            $data['client_id'] ?: null,
            $data['task_id'] ?: null,
            $data['content_id'] ?: null,
            $data['severity'] ?? 'medium',
            $data['status'] ?? 'assigned',
            $data['due_date'] ?: null
        ]);
        
        $id = (int)$db->lastInsertId();

        // 1. Notify the responsible person
        $deductionInfo = !empty($data['deduction_amount']) && (float)$data['deduction_amount'] > 0 
            ? " (Deduction: ₹" . number_format((float)$data['deduction_amount'], 2) . ")" 
            : "";
        Notifier::send(
            (int)$data['responsible_id'],
            'operations_issue',
            'Operations Issue Assigned: ' . $data['issue_title'],
            "An operational error has been assigned to you for rectification$deductionInfo. Target date: " . ($data['due_date'] ?: 'Urgent'),
            'operations_issue',
            $id
        );

        // 2. Notify ALL Founders/Owners immediately
        $founders = Database::all('SELECT id FROM users WHERE is_founder = 1 AND status = "active"');
        foreach ($founders as $f) {
            Notifier::send(
                (int)$f['id'],
                'operations_error_founder',
                '🚨 Operations Error Logged: ' . $data['issue_title'],
                "Operational error reported by " . Auth::name() . " assigned to user #{$data['responsible_id']}$deductionInfo. Severity: " . strtoupper($data['severity'] ?? 'medium'),
                'operations_issue',
                $id
            );
        }

        AuditLog::record('create', 'operations_issue', $id, null, $data['issue_title']);
        return $id;
    }

    public static function markCorrected(int $id, int $correctedById, string $notes): void
    {
        Database::run(
            'UPDATE operations_issues 
             SET status = "corrected", corrected_by_id = ?, correction_notes = ?, resolved_at = NOW() 
             WHERE id = ?',
            [$correctedById, $notes, $id]
        );

        $issue = self::find($id);
        if ($issue) {
            // Notify the person who noticed that it has been corrected
            Notifier::send(
                (int)$issue['noticed_by_id'],
                'operations_issue_corrected',
                'Operations Issue Corrected: ' . $issue['issue_title'],
                "Issue has been marked corrected by " . Auth::name() . ". Notes: $notes",
                'operations_issue',
                $id
            );

            // Notify founders that issue is rectified
            $founders = Database::all('SELECT id FROM users WHERE is_founder = 1 AND status = "active"');
            foreach ($founders as $f) {
                Notifier::send(
                    (int)$f['id'],
                    'operations_issue_rectified',
                    '✓ Operations Issue Rectified: ' . $issue['issue_title'],
                    "Rectified by " . Auth::name() . ". Notes: $notes",
                    'operations_issue',
                    $id
                );
            }
        }

        AuditLog::record('corrected', 'operations_issue', $id, null, $notes);
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE operations_issues 
             SET issue_title = ?, description = ?, responsible_id = ?, deduction_amount = ?, deduction_type = ?, client_id = ?, severity = ?, status = ?, due_date = ? 
             WHERE id = ?',
            [
                $data['issue_title'],
                $data['description'] ?: null,
                $data['responsible_id'],
                $data['deduction_amount'] ?? 0.00,
                $data['deduction_type'] ?? 'deduction',
                $data['client_id'] ?: null,
                $data['severity'] ?? 'medium',
                $data['status'] ?? 'assigned',
                $data['due_date'] ?: null,
                $id
            ]
        );
        AuditLog::record('update', 'operations_issue', $id, null, $data['issue_title']);
    }

    public static function checkDelaysAndEscalate(): void
    {
        try {
            // Detect issues past due date that are not corrected or escalated yet
            $delayedIssues = Database::all(
                "SELECT oi.*, u_resp.name AS resp_name 
                 FROM operations_issues oi
                 JOIN users u_resp ON u_resp.id = oi.responsible_id
                 WHERE oi.status NOT IN ('corrected', 'closed') 
                   AND oi.due_date IS NOT NULL 
                   AND oi.due_date < CURDATE() 
                   AND oi.is_delayed = 0"
            );

            if (!empty($delayedIssues)) {
                $founders = Database::all('SELECT id FROM users WHERE is_founder = 1 AND status = "active"');
                foreach ($delayedIssues as $d) {
                    Database::run(
                        "UPDATE operations_issues SET is_delayed = 1, status = 'escalated_to_founder', founder_escalated_at = NOW() WHERE id = ?",
                        [$d['id']]
                    );

                    foreach ($founders as $f) {
                        Notifier::send(
                            (int)$f['id'],
                            'operations_delayed_urgent',
                            '🚨 DELAY ESCALATION: Unresolved Operations Issue past Due Date!',
                            "Issue #{$d['id']}: '{$d['issue_title']}' assigned to {$d['resp_name']} is DELAYED past its target date ({$d['due_date']}). Please review immediately.",
                            'operations_issue',
                            (int)$d['id']
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            // If table does not exist or error occurs, fail silently
            return;
        }
    }

    public static function paginate(int $page, int $perPage, array $filters = []): array
    {
        self::checkDelaysAndEscalate();

        $where = ['1=1'];
        $params = [];

        // Scoping
        if (!Auth::hasRole('founder') && !Auth::hasRole('manager')) {
            $where[] = '(oi.responsible_id = ? OR oi.noticed_by_id = ?)';
            $params[] = Auth::id();
            $params[] = Auth::id();
        }

        if (!empty($filters['status'])) {
            $where[] = 'oi.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['severity'])) {
            $where[] = 'oi.severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['responsible_id'])) {
            $where[] = 'oi.responsible_id = ?';
            $params[] = (int)$filters['responsible_id'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'oi.client_id = ?';
            $params[] = (int)$filters['client_id'];
        }
        if (!empty($filters['delayed_only'])) {
            $where[] = "(oi.is_delayed = 1 OR (oi.due_date < CURDATE() AND oi.status NOT IN ('corrected','closed')))";
        }
        if (!empty($filters['search'])) {
            $where[] = '(oi.issue_title LIKE ? OR oi.description LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM operations_issues oi WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT oi.*, 
                    u_noticed.name AS noticed_by_name, 
                    u_resp.name AS responsible_name, 
                    u_corr.name AS corrected_by_name,
                    c.name AS client_name
             FROM operations_issues oi
             JOIN users u_noticed ON u_noticed.id = oi.noticed_by_id
             JOIN users u_resp ON u_resp.id = oi.responsible_id
             LEFT JOIN users u_corr ON u_corr.id = oi.corrected_by_id
             LEFT JOIN clients c ON c.id = oi.client_id
             WHERE $whereSql
             ORDER BY (oi.is_delayed = 1 OR oi.status = 'escalated_to_founder') DESC, oi.created_at DESC
             LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );

        return [$rows, $p];
    }

    public static function allDelayedOrAtRisk(): array
    {
        try {
            return Database::all(
                "SELECT oi.*, 
                        u_noticed.name AS noticed_by_name, 
                        u_resp.name AS responsible_name, 
                        c.name AS client_name
                 FROM operations_issues oi
                 JOIN users u_noticed ON u_noticed.id = oi.noticed_by_id
                 JOIN users u_resp ON u_resp.id = oi.responsible_id
                 LEFT JOIN clients c ON c.id = oi.client_id
                 WHERE oi.status NOT IN ('corrected', 'closed')
                   AND (oi.is_delayed = 1 OR oi.status = 'escalated_to_founder' OR (oi.due_date IS NOT NULL AND oi.due_date < CURDATE()))
                 ORDER BY oi.created_at ASC"
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}
